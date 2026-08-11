<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;

/**
 * Imports Bazar entries from a remote YesWiki's form into a local form.
 *
 * Two sync modes (config['syncMode']):
 * - 'source_of_truth': the local form/lists/entries are a continuous mirror
 *   of the remote ones (including deletions).
 * - 'allow_local': the local form may differ (field mapping via
 *   config['fieldsMapping'] once it pre-exists); lists are merged without
 *   ever removing local-only values; entries are created/updated but never
 *   deleted, and are skipped if they were edited locally since our last sync.
 *
 * Two file modes (config['filesMode']) for the values of the remote form's file/image fields,
 * which are file names relative to the remote wiki's upload directory and therefore point at
 * nothing locally if copied as-is:
 * - 'download': the files are downloaded into the local upload directory.
 * - 'url': the local entries keep an absolute url to the file on the source wiki.
 */
class YesWikiToYesWikiImporter extends Importer
{
    protected string $source;
    protected string $cookie = '';
    /** @var array<string, mixed> the remote form, as its api answered it */
    protected array $remoteForm = [];
    protected bool $localFormExists = false;
    /** @var array<string, array{0: string, 1: string}> [remote list tag, local list tag] */
    protected array $listTagPairs = [];
    /** @var list<string> local keys of the fields holding a file name */
    protected array $fileFieldKeys = [];
    /** @var array<string, mixed>|null who was logged in before we impersonated an admin */
    protected $impersonationPreviousUser;

    public function __construct(
        string $source,
        ParameterBagInterface $params,
        ContainerInterface $services,
        EntryManager $entryManager,
        ImporterManager $importerManager,
        FormManager $formManager,
        ListManager $listManager
    ) {
        $this->source = $source;
        $this->params = $params;
        $this->services = $services;
        $this->entryManager = $entryManager;
        $this->importerManager = $importerManager;
        $this->formManager = $formManager;
        $this->listManager = $listManager;
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];
        $sourceOptions = is_array($dataSources) ? ($dataSources[$this->source] ?? []) : [];
        $this->config = $this->checkConfig(is_array($sourceOptions) ? $sourceOptions : []);
    }

    /**
     * Check if config input is good enough to be used by Importer.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> checked config
     */
    public function checkConfig(array $config)
    {
        $config = parent::checkConfig($config);
        // "url" is the remote form's entries API url, which already carries the remote form id
        // (and, optionally, the query parameters restricting which entries to import)
        $parsedUrl = !empty($config['url']) ? self::parseEntriesUrl($config['url']) : null;
        if (!empty($parsedUrl)) {
            $config = array_merge($config, $parsedUrl);
        }
        foreach (['url', 'formId', 'syncMode'] as $key) {
            if (empty($config[$key])) {
                throw new \Exception('Le paramètre "' . $key . '" est requis pour un importer YesWikiToYesWiki.');
            }
        }
        if (empty($config['remoteFormId'])) {
            throw new \Exception('Le paramètre "url" doit être l\'url de l\'api des fiches du formulaire distant, de la forme "https://mon-wiki-distant.fr/?api/forms/12/entries/json".');
        }
        // Credentials are OPTIONAL (ticket 34). They used to be required, which was fine while
        // `{{entrylist id="https://other.wiki|4"}}` existed to cover the public case -- that read
        // a remote wiki's public API with no account at all. Removing it and keeping auth
        // mandatory would have meant every wiki displaying public remote entries needed an
        // account on the remote wiki before it could upgrade: a regression introduced by a
        // cleanup. With neither set, the remote API is read anonymously and a private form simply
        // answers nothing, which is the same outcome as wrong credentials.
        //
        // Half-configured is still an error, because it is always a mistake rather than a choice.
        $user = $config['auth']['user'] ?? '';
        $password = $config['auth']['password'] ?? '';
        if (empty($user) xor empty($password)) {
            throw new \Exception('Les paramètres "auth.user" et "auth.password" doivent être fournis ensemble, ou tous les deux laissés vides pour lire un formulaire distant public.');
        }
        if (!in_array($config['syncMode'], ['source_of_truth', 'allow_local'], true)) {
            throw new \Exception('Le paramètre "syncMode" doit valoir "source_of_truth" ou "allow_local".');
        }
        $config['filesMode'] = $config['filesMode'] ?? 'download';
        if (!in_array($config['filesMode'], ['download', 'url'], true)) {
            throw new \Exception('Le paramètre "filesMode" doit valoir "download" ou "url".');
        }

        return $config;
    }

    public static function getAdminFields(): array
    {
        return [
            'url' => [
                'type' => 'url',
                'required' => true,
                'label' => 'IMPORTER_FIELD_YESWIKITOYESWIKI_URL',
                'help' => 'IMPORTER_FIELD_YESWIKITOYESWIKI_URL_HELP',
            ],
            // optional: leave both empty to read a public remote form anonymously (ticket 34)
            'auth_user' => [
                'type' => 'text',
                'required' => false,
                'help' => 'IMPORTER_FIELD_YESWIKITOYESWIKI_AUTH_HELP',
            ],
            'auth_password' => ['type' => 'password', 'required' => false],
            'localAdminUser' => ['type' => 'text', 'required' => false],
            'syncMode' => [
                'type' => 'select',
                'required' => true,
                'options' => [
                    'source_of_truth' => 'IMPORTER_SYNCMODE_SOURCE_OF_TRUTH',
                    'allow_local' => 'IMPORTER_SYNCMODE_ALLOW_LOCAL',
                ],
            ],
            'filesMode' => [
                'type' => 'select',
                'required' => true,
                'options' => [
                    'download' => 'IMPORTER_FILESMODE_DOWNLOAD',
                    'url' => 'IMPORTER_FILESMODE_URL',
                ],
            ],
            'noSSLCheck' => ['type' => 'checkbox', 'required' => false],
            'timeoutInSec' => ['type' => 'number', 'required' => false],
        ];
    }

    public static function hasRemoteFieldMapping(): bool
    {
        return true;
    }

    /**
     * The admin pastes a single url, the remote form's entries API url (as displayed by the
     * remote wiki itself, e.g. https://mon-wiki.fr/?api/forms/12/entries/json), so there is no
     * separate remote form id to ask for. Split it into what the importer actually works with:
     * the remote wiki's base url (which every other api call is built from), the remote form
     * id, and the extra query parameters, if any, to forward when fetching the entries
     * (&query=... to import only a subset of the remote form's entries).
     */
    public static function normalizeAdminOptions(array $options): array
    {
        $parsed = !empty($options['url']) ? self::parseEntriesUrl($options['url']) : null;
        if (empty($parsed)) {
            // leave the url as typed: checkConfig() reports what's expected at sync time
            return $options;
        }
        if (empty($parsed['entriesQuery'])) {
            unset($parsed['entriesQuery'], $options['entriesQuery']);
        }

        return array_merge($options, $parsed);
    }

    /**
     * Rebuild the entries API url the admin pasted, to prefill the edit form with.
     */
    public static function denormalizeAdminOptions(array $options): array
    {
        if (empty($options['url']) || empty($options['remoteFormId'])) {
            return $options;
        }
        $options['url'] = rtrim($options['url'], '/') . '/?api/forms/' . $options['remoteFormId'] . '/entries/json'
            . (empty($options['entriesQuery']) ? '' : '&' . $options['entriesQuery']);

        return $options;
    }

    /**
     * Extract ['url' => remote wiki base url, 'remoteFormId' => ..., 'entriesQuery' => ...]
     * from a remote form's entries API url, or null if that's not what was given.
     * All the url shapes a YesWiki can produce are accepted: the usual "?api/..." shortcut,
     * its "?wiki=api/..." long form, and the rewritten "/api/..." path, each possibly under a
     * subfolder (for wikis not installed at their domain's root).
     *
     * @return array{url: string, remoteFormId: string, entriesQuery: string}|null
     */
    public static function parseEntriesUrl(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if (empty($parts) || empty($parts['host'])) {
            return null;
        }
        $path = $parts['path'] ?? '/';
        $handler = '';
        $extraParams = [];
        foreach (array_filter(explode('&', $parts['query'] ?? '')) as $param) {
            if ($handler === '' && (strpos($param, 'api/') === 0 || strpos($param, 'wiki=api/') === 0)) {
                $handler = preg_replace('~^wiki=~', '', $param);
            } else {
                $extraParams[] = $param;
            }
        }
        $handlerIsPath = ($handler === '');
        if ($handlerIsPath) {
            $handler = $path;
        }
        if (!preg_match('~(?:^|/)api/forms/([^/?&]+)/entries~', rawurldecode((string)$handler), $matches)) {
            return null;
        }
        $basePath = $path;
        if ($handlerIsPath) {
            $apiPos = strpos($path, 'api/forms');
            $basePath = $apiPos === false ? '' : substr($path, 0, $apiPos);
        }
        // "https://mon-wiki.fr/index.php?api/..." : the entry script is not part of the base url
        $basePath = preg_replace('~/[^/]*\.php$~', '', rtrim($basePath, '/'));

        return [
            'url' => ($parts['scheme'] ?? 'https') . '://' . $parts['host']
                . (!empty($parts['port']) ? ':' . $parts['port'] : '') . $basePath,
            'remoteFormId' => $matches[1],
            'entriesQuery' => implode('&', $extraParams),
        ];
    }

    /**
     * Sign in on the source wiki, if credentials were configured.
     *
     * With none, nothing is sent and `$this->cookie` stays empty: the remote API is read
     * anonymously, which is all a public form needs (ticket 34).
     */
    public function authenticate(): void
    {
        if (empty($this->config['auth']['user']) || empty($this->config['auth']['password'])) {
            $this->cookie = '';

            return;
        }

        $response = $this->importerManager->curl(
            $this->remoteUrl('api/login'),
            ['Content-Type: application/x-www-form-urlencoded'],
            true,
            http_build_query([
                'username' => $this->config['auth']['user'],
                'password' => $this->config['auth']['password'],
            ]),
            $this->noSSLCheck(),
            true,
            $this->timeoutInSec()
        );
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', (string)$response, $matches);
        $this->cookie = implode('; ', $matches[1]);
    }

    /**
     * @return array{form: array<string, mixed>, entries: array<mixed>}
     */
    public function getData(): array
    {
        $this->authenticate();
        $entriesPath = 'api/forms/' . $this->config['remoteFormId'] . '/entries';
        if (!empty($this->config['entriesQuery'])) {
            $entriesPath .= '&' . $this->config['entriesQuery'];
        }
        $form = $this->remoteGet('api/forms/' . $this->config['remoteFormId']);
        $entries = $this->remoteGet($entriesPath);

        return [
            'form' => is_array($form) ? $form : [],
            'entries' => is_array($entries) ? $entries : [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mapData(mixed $data): array
    {
        $this->remoteForm = $data['form'] ?? [];
        $remoteEntries = $data['entries'] ?? [];
        $isSourceOfTruth = $this->config['syncMode'] === 'source_of_truth';

        // `template` from a wiki of this generation, `bn_template` from a doryphore one
        $remoteTemplate = $this->remoteForm['template'] ?? $this->remoteForm['bn_template'] ?? '';
        $remoteFieldsByProperty = $this->fieldsByProperty(
            is_array($remoteTemplate) ? $remoteTemplate : $this->formManager->parseTemplate($remoteTemplate)
        );

        $localForm = $this->formManager->getOne($this->config['formId']);
        $this->localFormExists = !empty($localForm);

        if (!$this->localFormExists || $isSourceOfTruth) {
            // local mirrors remote field-for-field
            $mapping = [];
            foreach ($remoteFieldsByProperty as $propertyName => $field) {
                $mapping[$propertyName] = $propertyName;
            }
            $this->listTagPairs = [];
            foreach ($remoteFieldsByProperty as $field) {
                if ($this->isListBackedField($field)) {
                    $tag = $field->getLinkedObjectName();
                    if (!empty($tag)) {
                        $this->listTagPairs[$tag . '|' . $tag] = [$tag, $tag];
                    }
                }
            }
        } else {
            $mapping = $this->config['fieldsMapping'] ?? [];
            if (empty($mapping)) {
                throw new \Exception('Le paramètre "fieldsMapping" est requis : le formulaire local existe déjà et le mode "allow_local" nécessite une correspondance de champs (configurable depuis {{adminimporters}}).');
            }
            $localFieldsByProperty = $this->fieldsByProperty($localForm['template'] ?? []);
            $this->listTagPairs = [];
            foreach ($mapping as $remoteField => $localField) {
                $remoteFieldObj = $remoteFieldsByProperty[$remoteField] ?? null;
                $localFieldObj = $localFieldsByProperty[$localField] ?? null;
                if ($this->isListBackedField($remoteFieldObj) && $this->isListBackedField($localFieldObj)) {
                    $remoteTag = $remoteFieldObj->getLinkedObjectName();
                    $localTag = $localFieldObj->getLinkedObjectName();
                    if (!empty($remoteTag) && !empty($localTag)) {
                        $this->listTagPairs[$remoteTag . '|' . $localTag] = [$remoteTag, $localTag];
                    }
                }
            }
        }

        // file/image fields hold a file name relative to the remote wiki's upload directory:
        // remember where they land locally, syncData() turns them into something the local
        // wiki can actually serve (a downloaded file, or an url to the source wiki)
        $this->fileFieldKeys = [];
        foreach ($mapping as $remoteField => $localField) {
            if ($this->isFileBackedField($remoteFieldsByProperty[$remoteField] ?? null)) {
                $this->fileFieldKeys[] = $localField;
            }
        }

        $mappedEntries = [];
        foreach ($remoteEntries as $remoteEntry) {
            if (!is_array($remoteEntry) || empty($remoteEntry['url'])) {
                continue;
            }
            $mappedEntry = [];
            foreach ($mapping as $remoteField => $localField) {
                if (array_key_exists($remoteField, $remoteEntry)) {
                    $mappedEntry[$localField] = $remoteEntry[$remoteField];
                }
            }
            $mappedEntry['_remote_url'] = $remoteEntry['url'];
            // `updated_at` here, `date_maj_fiche` from a doryphore wiki (LEGACY_ENTRY_KEYS)
            $mappedEntry['_remote_updated_at'] = $remoteEntry['updated_at'] ?? $remoteEntry['date_maj_fiche'] ?? null;
            $mappedEntries[] = $mappedEntry;
        }

        return $mappedEntries;
    }

    public function syncFormModel(): void
    {
        if ($this->localFormExists) {
            if ($this->config['syncMode'] === 'source_of_truth') {
                $this->formManager->update($this->buildLocalFormData());
                echo 'Le formulaire local a été synchronisé (miroir) avec le formulaire distant.' . "\n";
            } else {
                echo 'Le formulaire local existe déjà, aucune modification (mode allow_local).' . "\n";
            }

            return;
        }
        $this->formManager->create($this->buildLocalFormData());
        echo 'Le formulaire local a été créé à partir du formulaire distant.' . "\n";
    }

    /**
     * @param array<mixed> $data
     */
    public function syncData(array $data): void
    {
        $impersonating = $this->impersonateLocalAdmin();
        try {
            $this->doSyncData($data);
        } finally {
            $this->endImpersonation($impersonating);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function doSyncData(array $data): void
    {
        $isSourceOfTruth = $this->config['syncMode'] === 'source_of_truth';

        foreach ($this->listTagPairs as [$remoteTag, $localTag]) {
            $this->syncList($remoteTag, $localTag, $isSourceOfTruth);
        }

        $seenLocalIds = [];
        $unchangedCount = 0;
        foreach ($data as $mappedEntry) {
            $remoteUrl = $mappedEntry['_remote_url'];
            $remoteUpdatedAt = $mappedEntry['_remote_updated_at'] ?? null;
            unset($mappedEntry['_remote_url'], $mappedEntry['_remote_updated_at']);
            $title = $mappedEntry['title'] ?? $mappedEntry['bf_titre'] ?? $remoteUrl;
            // required by EntryManager::validate() on both create() and update()
            $mappedEntry['antispam'] = 1;

            try {
                $localId = $this->findLocalEntryByRemoteUrl($remoteUrl);
                $localEntry = empty($localId) ? null : $this->entryManager->getOne($localId);
                if (!empty($localId) && empty($localEntry)) {
                    // stale mapping: the tripleStore still links this remote url to a local
                    // entry that no longer exists (deleted outside this sync, or left over
                    // from an earlier run) - clear it and treat it as "not found" instead of
                    // crashing update() with a null $previousData; otherwise this dead mapping
                    // would keep winning the lookup and re-trigger this same fallback (and
                    // create a fresh duplicate entry) on every future sync
                    $this->getService(TripleStore::class)->delete($localId, TripleStore::SOURCE_URL_URI, null, '', '');
                    $localId = null;
                }

                if (empty($localId)) {
                    $created = $this->entryManager->create($this->config['formId'], $this->importEntryFiles($mappedEntry), false, $remoteUrl);
                    if (!empty($created['tag'])) {
                        $seenLocalIds[] = $created['tag'];
                        $this->markSynced($created['tag'], date('Y-m-d H:i:s'));
                        echo 'Entrée "' . $title . '" créée.' . "\n";
                    }
                    continue;
                }

                $seenLocalIds[] = $localId;

                if ($isSourceOfTruth) {
                    $newValues = $this->importEntryFiles($mappedEntry);
                    $changed = $this->changedFields($localEntry, $newValues);
                    if (empty($changed)) {
                        $unchangedCount++;
                        continue;
                    }
                    if ($remoteUpdatedAt) {
                        $newValues['updated_at'] = $remoteUpdatedAt;
                    }
                    $this->entryManager->update($localId, $newValues, false, true);
                    echo 'Entrée "' . $title . '" mise à jour (miroir) : ' . implode(', ', $changed) . '.' . "\n";
                    continue;
                }

                // allow_local: skip if a human edited the entry locally since our last write
                $lastSync = $this->getLastSyncTime($localId);
                $localUpdatedAt = $localEntry['updated_at'] ?? null;
                if ($lastSync !== null && $localUpdatedAt !== null && $localUpdatedAt > $lastSync) {
                    echo 'Entrée "' . $title . '" modifiée localement, non synchronisée.' . "\n";
                    continue;
                }
                $newValues = $this->importEntryFiles($mappedEntry);
                $changed = $this->changedFields($localEntry, $newValues);
                if (empty($changed)) {
                    $unchangedCount++;
                    continue;
                }
                $this->entryManager->update($localId, $newValues, false, true);
                $this->markSynced($localId, date('Y-m-d H:i:s'));
                echo 'Entrée "' . $title . '" mise à jour : ' . implode(', ', $changed) . '.' . "\n";
            } catch (\Throwable $ex) {
                // one invalid/incompatible remote entry must not abort the whole sync batch
                echo 'Erreur sur l\'entrée "' . $title . '" : ' . $ex->getMessage() . "\n";
            }
        }

        if ($unchangedCount > 0) {
            echo $unchangedCount . ' entrée(s) déjà à jour, non réécrite(s).' . "\n";
        }

        if ($isSourceOfTruth) {
            $existingEntries = $this->entryManager->search(['formsIds' => [$this->config['formId']]]);
            foreach ($existingEntries as $entry) {
                if (!in_array($entry['tag'], $seenLocalIds, true)) {
                    $this->deleteLocalEntry($entry['tag'], $entry['title'] ?? $entry['tag']);
                }
            }
        }
    }

    // HELPERS

    private function noSSLCheck(): bool
    {
        return !empty($this->config['noSSLCheck']);
    }

    // a full form/entries/list dump can be large, the default 10s curl timeout is often too short
    private function timeoutInSec(): int
    {
        return !empty($this->config['timeoutInSec']) ? (int)$this->config['timeoutInSec'] : 120;
    }

    private function remoteUrl(string $path): string
    {
        return rtrim($this->config['url'], '/') . '/?' . ltrim($path, '/');
    }

    /**
     * @return mixed the decoded json answer, or null when the request or the decoding failed
     */
    private function remoteGet(string $path)
    {
        $response = $this->importerManager->curl(
            $this->remoteUrl($path),
            // no header at all when reading anonymously -- an empty `Cookie:` is not the same
            // thing as not sending one, and some servers treat it as a malformed request
            $this->cookie === '' ? [] : ['Cookie: ' . $this->cookie],
            false,
            [],
            $this->noSSLCheck(),
            false,
            $this->timeoutInSec()
        );

        return json_decode((string)$response, true);
    }

    /**
     * True for a field backed by a YesWiki List (checkbox/radio/liste), false for the "*fiche"
     * variants (checkboxfiche/radiofiche/listefiche) that link to another Bazar form's entries
     * instead: both share the EnumField base class and its getLinkedObjectName(), but only the
     * former points to an actual List page tag.
     *
     * @phpstan-assert-if-true EnumField $field
     */
    private function isListBackedField(mixed $field): bool
    {
        return $field instanceof EnumField && !$field->isEnumEntryField();
    }

    /**
     * True for a field whose value is an attached file name (image, fichier, and any field
     * deriving from them). Matched on the short class name rather than with instanceof: the
     * bazar fields' namespace changed between YesWiki versions (Bazar\Field, Core\Field,
     * Content\Field...) while their class names did not.
     */
    private function isFileBackedField(mixed $field): bool
    {
        for ($class = is_object($field) ? get_class($field) : ''; !empty($class); $class = get_parent_class($class)) {
            $shortName = substr((string)strrchr('\\' . $class, '\\'), 1);
            if (in_array($shortName, ['FileField', 'ImageField'], true)) {
                return true;
            }
        }

        return false;
    }

    private function filesMode(): string
    {
        return $this->config['filesMode'] ?? 'download';
    }

    /**
     * Replace, in an entry about to be written locally, each remote file name by something the
     * local wiki can serve: either the name of a copy downloaded into the local upload
     * directory, or an absolute url to the file left on the source wiki (which the file/image
     * fields of recent YesWiki versions accept as a value).
     *
     * @param array<string, mixed> $mappedEntry
     *
     * @return array<string, mixed>
     */
    private function importEntryFiles(array $mappedEntry): array
    {
        foreach ($this->fileFieldKeys as $key) {
            $value = $mappedEntry[$key] ?? null;
            // an already absolute url is a file the remote entry itself did not host: keep it
            if (empty($value) || !is_string($value) || filter_var($value, FILTER_VALIDATE_URL) !== false) {
                continue;
            }
            $remoteFileUrl = $this->remoteFileUrl($value);
            if ($this->filesMode() === 'url') {
                $mappedEntry[$key] = $remoteFileUrl;
                continue;
            }
            $localFileName = $this->importerManager->downloadFile(
                $remoteFileUrl,
                $this->noSSLCheck(),
                $this->timeoutInSec(),
                false,
                $value
            );
            // an entry pointing at a file that isn't there is worse than one without a file:
            // the field would be emptied on the next local edit anyway
            $mappedEntry[$key] = $localFileName;
        }

        return $mappedEntry;
    }

    /**
     * Url of a file attached to a remote entry. The remote upload directory is "files" unless
     * that wiki changed its attach_config['upload_path'], which no api exposes: config
     * 'remoteFilesPath' is there for that (rare) case.
     */
    private function remoteFileUrl(string $fileName): string
    {
        $remotePath = trim($this->config['remoteFilesPath'] ?? 'files', '/');

        return rtrim($this->config['url'], '/') . '/' . $remotePath . '/' . rawurlencode($fileName);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRemotePage(string $tag): ?array
    {
        $page = $this->remoteGet('api/pages/' . rawurlencode($tag));
        if (empty($page['body'])) {
            echo 'Impossible de récupérer la page distante "' . $tag . '" (introuvable, non accessible, ou réponse invalide).' . "\n";

            return null;
        }
        $decoded = json_decode($page['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        // lists saved before the 2024 {title,nodes} format still store {titre_liste,label} on
        // disk: ListManager::getOne() normalizes this transparently for local reads, but here
        // we fetch the remote page's raw body directly, so we need the same conversion.
        return $this->listManager->convertDataStructure($decoded);
    }

    /**
     * @param array        $template a form's fields, either as this wiki stores them (named field
     *                               objects) or as FormManager::parseTemplate() returns them from a
     *                               remote wiki's raw template (positional arrays, ADR-0009)
     * @param array<mixed> $template
     *
     * @return array<string, BazarField> [propertyName => BazarField]
     */
    private function fieldsByProperty(array $template): array
    {
        $fields = $this->formManager->prepareData([
            'template' => $this->formManager->positionalListToTemplate($template),
        ]);
        $byProperty = [];
        foreach ($fields as $field) {
            $propertyName = $field ? $field->getPropertyName() : null;
            // skip layout-only fields (tabs, labelhtml, acls, ...): they have no property name
            if (!empty($propertyName)) {
                $byProperty[$propertyName] = $field;
            }
        }

        return $byProperty;
    }

    /**
     * The remote form, in the shape FormManager::create()/update() take.
     *
     * The remote is very often a doryphore wiki, whose api answers with the old `bn_*`
     * properties and a `***`-separated template string; a wiki of this generation answers
     * with the English ones (ADR-0010) and a JSON template. Both are read here, and
     * FormManager stores whichever arrived in this wiki's own format -- parseTemplate()
     * accepts either syntax for exactly this reason.
     *
     * The form always describes ordinary entries: a built-in Content type (page, user, file)
     * has exactly one form per wiki, which a remote mirror must not become a second of.
     *
     * @return array<string, mixed>
     */
    private function buildLocalFormData(): array
    {
        $remote = $this->remoteForm;

        return [
            'id' => $this->config['formId'],
            'label' => $remote['label'] ?? $remote['bn_label_nature'] ?? '',
            'template' => $remote['template'] ?? $remote['bn_template'] ?? '',
            'description' => $remote['description'] ?? $remote['bn_description'] ?? '',
            ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_ENTRY,
            'sem_template' => $remote['sem_template'] ?? $remote['bn_sem_template'] ?? '',
            'sem_reverse_template' => $remote['sem_reverse_template'] ?? $remote['bn_sem_reverse_template'] ?? '',
            // ActivityPub identity/keys are never copied from a remote instance
            'activitypub_username' => '',
            'condition' => $remote['condition'] ?? $remote['bn_condition'] ?? '',
            'only_one_entry' => $remote['only_one_entry'] ?? $remote['bn_only_one_entry'] ?? 'N',
            'only_one_entry_message' => $remote['only_one_entry_message'] ?? $remote['bn_only_one_entry_message'] ?? '',
        ];
    }

    private function syncList(string $remoteTag, string $localTag, bool $mirror): void
    {
        $remoteList = $this->fetchRemotePage($remoteTag);
        if (empty($remoteList)) {
            return;
        }
        $remoteNodes = $remoteList['nodes'] ?? [];
        $remoteTitle = $remoteList['title'] ?? $localTag;
        $exists = $this->listManager->isList($localTag);

        if ($mirror) {
            if ($exists) {
                $this->listManager->update($localTag, $remoteTitle, $remoteNodes);
            } else {
                $this->listManager->create($remoteTitle, $remoteNodes, $localTag);
            }
            echo 'Liste "' . $localTag . '" synchronisée (miroir) avec ' . count($remoteNodes) . ' valeur(s).' . "\n";

            return;
        }

        // allow_local: non-destructive union, local-only values are never removed
        $existingList = $exists ? $this->listManager->getOne($localTag) : null;
        $byId = [];
        foreach (($existingList['nodes'] ?? []) as $node) {
            $byId[$node['id']] = $node;
        }
        foreach ($remoteNodes as $node) {
            $byId[$node['id']] = $node;
        }
        $mergedNodes = array_values($byId);
        $title = $existingList['title'] ?? $remoteTitle;
        if ($exists) {
            $this->listManager->update($localTag, $title, $mergedNodes);
        } else {
            $this->listManager->create($title, $mergedNodes, $localTag);
        }
        echo 'Liste "' . $localTag . '" fusionnée (total local : ' . count($mergedNodes) . ' valeur(s)).' . "\n";
    }

    /**
     * The fields writing $newValues would actually change in the local entry (empty: nothing
     * to do). A remote entry that didn't move must not be rewritten: EntryManager::update()
     * unconditionally saves a new page revision and stamps updated_at with the current time,
     * so an unconditional update makes every sync report changes that never happened,
     * grows the pages table by one revision per entry per run, and (in allow_local) keeps
     * moving the local "last modified" date the local-edit detection relies on.
     * Only the keys we are about to write are compared: fields the local form has but the
     * mapping doesn't cover are none of our business.
     *
     * @param array<string, mixed>|null $localEntry
     * @param array<string, mixed>      $newValues
     *
     * @return list<string>
     */
    private function changedFields(?array $localEntry, array $newValues): array
    {
        if (empty($localEntry)) {
            return array_keys($newValues);
        }
        $changed = [];
        foreach ($newValues as $key => $value) {
            // bookkeeping, not content: 'antispam' is only there to pass validate(), and
            // updated_at is precisely what an update would (re)write
            if (in_array($key, ['antispam', 'updated_at'], true)) {
                continue;
            }
            if ($this->comparableValue($value) !== $this->comparableValue($localEntry[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * A field's value as a string that can be compared between what the remote api returned
     * (json: nulls, numbers, and multi-valued fields possibly as arrays) and what bazar stored
     * locally (always strings, multi-valued fields comma separated).
     */
    private function comparableValue(mixed $value): string
    {
        if (is_array($value)) {
            $value = implode(',', array_map(function ($item) {
                return is_scalar($item) ? (string)$item : json_encode($item);
            }, array_values($value)));
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '';
        } elseif ($value === null) {
            $value = '';
        } elseif (!is_scalar($value)) {
            $value = json_encode($value);
        }

        return trim((string)$value);
    }

    private function findLocalEntryByRemoteUrl(string $remoteUrl): ?string
    {
        $matches = $this->getService(TripleStore::class)->getMatching(null, TripleStore::SOURCE_URL_URI, $remoteUrl);

        return $matches[0]['resource'] ?? null;
    }

    private function syncTimeProperty(): string
    {
        return 'sync_last_sync_time_' . $this->source;
    }

    private function getLastSyncTime(string $localId): ?string
    {
        return $this->getService(TripleStore::class)->getOne($localId, $this->syncTimeProperty(), '', '');
    }

    private function markSynced(string $localId, string $now): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $property = $this->syncTimeProperty();
        $existing = $tripleStore->getOne($localId, $property, '', '');
        if ($existing === null) {
            $tripleStore->create($localId, $property, $now, '', '');
        } else {
            $tripleStore->update($localId, $property, $existing, $now, '', '');
        }
    }

    /**
     * EntryManager::update() (unlike create(), which always bypasses ACLs) enforces the
     * write ACL against the current logged-in user, and our CLI/console sync normally runs
     * with no logged-in user at all: without impersonating a local admin, every update on a
     * pre-existing entry would be rejected.
     */
    private function impersonateLocalAdmin(): bool
    {
        if (empty($this->config['localAdminUser'])) {
            echo 'Aucun "localAdminUser" configuré : la mise à jour de fiches existantes ' .
                'échouera si leurs droits d\'écriture ne sont pas publics.' . "\n";

            return false;
        }
        $adminUser = $this->getService(UserManager::class)->getOneByName($this->config['localAdminUser']);
        if (!$adminUser) {
            echo 'Utilisateur local "' . $this->config['localAdminUser'] . '" introuvable : ' .
                'la mise à jour de fiches existantes risque d\'échouer par manque de droits.' . "\n";

            return false;
        }
        $authenticationService = $this->getService(AuthenticationService::class);
        $this->impersonationPreviousUser = $authenticationService->getLoggedUser();
        $authenticationService->login($adminUser);

        return true;
    }

    private function endImpersonation(bool $wasImpersonating): void
    {
        if (!$wasImpersonating) {
            return;
        }
        $authenticationService = $this->getService(AuthenticationService::class);
        $authenticationService->logout();
        if (!empty($this->impersonationPreviousUser)) {
            $authenticationService->login($this->impersonationPreviousUser);
        }
    }

    private function deleteLocalEntry(string $localId, string $title): void
    {
        try {
            $this->getService(PageManager::class)->deleteOrphaned($localId);
            $tripleStore = $this->getService(TripleStore::class);
            $tripleStore->delete($localId, TripleStore::TYPE_URI, null, '', '');
            $tripleStore->delete($localId, TripleStore::SOURCE_URL_URI, null, '', '');
            $tripleStore->delete($localId, $this->syncTimeProperty(), null, '', '');
            echo 'Entrée "' . $title . '" supprimée (disparue de la source).' . "\n";
        } catch (\Throwable $ex) {
            echo 'Erreur lors de la suppression de "' . $title . '" : ' . $ex->getMessage() . "\n";
        }
    }
}
