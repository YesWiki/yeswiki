<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\TripleStore;

/** Imports Bazar entries from a remote YesWiki's form into a local form. */
class YesWikiToYesWikiImporter extends Importer
{
    protected string $source;
    protected string $cookie = '';
    /**
     * @var array<string, mixed> the remote form, as its api answered it
     */
    protected array $remoteForm = [];
    protected bool $localFormExists = false;
    /**
     * @var array<string, array{0: string, 1: string}> [remote list tag, local list tag]
     */
    protected array $listTagPairs = [];
    /**
     * @var list<string> local keys of the fields holding a file name
     */
    protected array $fileFieldKeys = [];
    /**
     * @var array<string, mixed>|null who was logged in before we impersonated an admin
     */
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
     * The admin pastes a single url, the remote form's entries API url (as displayed by the remote wiki itself, e.g.
     */
    public static function normalizeAdminOptions(array $options): array
    {
        $parsed = !empty($options['url']) ? self::parseEntriesUrl($options['url']) : null;
        if (empty($parsed)) {
            return $options;
        }
        if (empty($parsed['entriesQuery'])) {
            unset($parsed['entriesQuery'], $options['entriesQuery']);
        }

        return array_merge($options, $parsed);
    }

    /** Rebuild the entries API url the admin pasted, to prefill the edit form with. */
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
     * Extract ['url' => remote wiki base url, 'remoteFormId' => ..., 'entriesQuery' => ...] from a remote form's entries API url, or null if that's not what was given.
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

        $basePath = preg_replace('~/[^/]*\.php$~', '', rtrim($basePath, '/'));

        return [
            'url' => ($parts['scheme'] ?? 'https') . '://' . $parts['host']
                . (!empty($parts['port']) ? ':' . $parts['port'] : '') . $basePath,
            'remoteFormId' => $matches[1],
            'entriesQuery' => implode('&', $extraParams),
        ];
    }

    /** Sign in on the source wiki, if credentials were configured. */
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

        $remoteTemplate = $this->remoteForm['template'] ?? $this->remoteForm['bn_template'] ?? '';
        $remoteFieldsByProperty = $this->fieldsByProperty(
            is_array($remoteTemplate) ? $remoteTemplate : $this->formManager->parseTemplate($remoteTemplate)
        );

        $localForm = $this->formManager->getOne($this->config['formId']);
        $this->localFormExists = !empty($localForm);

        if (!$this->localFormExists || $isSourceOfTruth) {
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

            $mappedEntry['antispam'] = 1;

            try {
                $localId = $this->findLocalEntryByRemoteUrl($remoteUrl);
                $localEntry = empty($localId) ? null : $this->entryManager->getOne($localId);
                if (!empty($localId) && empty($localEntry)) {
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

    private function noSSLCheck(): bool
    {
        return !empty($this->config['noSSLCheck']);
    }

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
     * True for a field backed by a YesWiki List (checkbox/radio/liste), false for the "*fiche" variants (checkboxfiche/radiofiche/listefiche) that link to another Bazar form's entries instead: both share the EnumField base class and its getLinkedObjectName(), but only the former points to an actual List page tag.
     *
     * @phpstan-assert-if-true EnumField $field
     */
    private function isListBackedField(mixed $field): bool
    {
        return $field instanceof EnumField && !$field->isEnumEntryField();
    }

    /**
     * True for a field whose value is an attached file name (image, fichier, and any field deriving from them).
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
     * Replace, in an entry about to be written locally, each remote file name by something the local wiki can serve: either the name of a copy downloaded into the local upload directory, or an absolute url to the file left on the source wiki (which the file/image fields of recent YesWiki versions accept as a value).
     *
     * @param array<string, mixed> $mappedEntry
     *
     * @return array<string, mixed>
     */
    private function importEntryFiles(array $mappedEntry): array
    {
        foreach ($this->fileFieldKeys as $key) {
            $value = $mappedEntry[$key] ?? null;

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

            $mappedEntry[$key] = $localFileName;
        }

        return $mappedEntry;
    }

    /** Url of a file attached to a remote entry. */
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

            if (!empty($propertyName)) {
                $byProperty[$propertyName] = $field;
            }
        }

        return $byProperty;
    }

    /**
     * The remote form, in the shape FormManager::create()/update() take.
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
     * The fields writing $newValues would actually change in the local entry (empty: nothing to do).
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
     * A field's value as a string that can be compared between what the remote api returned (json: nulls, numbers, and multi-valued fields possibly as arrays) and what bazar stored locally (always strings, multi-valued fields comma separated).
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
     * EntryManager::update() (unlike create(), which always bypasses ACLs) enforces the write ACL against the current logged-in user, and our CLI/console sync normally runs with no logged-in user at all: without impersonating a local admin, every update on a pre-existing entry would be rejected.
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
