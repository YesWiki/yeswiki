<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
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
#[Autoconfigure(autowire: false)]
class YesWikiToYesWikiImporter extends Importer
{
    private const PASS_KEYS = ['antispam', 'created_at', 'updated_at'];

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
        $config['conflictPolicy'] = $config['conflictPolicy'] ?? EntryMerger::POLICY_LOCAL;
        if (!in_array($config['conflictPolicy'], [EntryMerger::POLICY_LOCAL, EntryMerger::POLICY_REMOTE], true)) {
            throw new \Exception('Le paramètre "conflictPolicy" doit valoir "local" ou "remote".');
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
            'conflictPolicy' => [
                'type' => 'select',
                'required' => false,
                'help' => 'IMPORTER_FIELD_CONFLICTPOLICY_HELP',
                'options' => [
                    EntryMerger::POLICY_LOCAL => 'IMPORTER_CONFLICTPOLICY_LOCAL',
                    EntryMerger::POLICY_REMOTE => 'IMPORTER_CONFLICTPOLICY_REMOTE',
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
            'keepRemoteUpdateDate' => [
                'type' => 'checkbox',
                'required' => false,
                'help' => 'IMPORTER_FIELD_KEEPREMOTEUPDATEDATE_HELP',
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
     * Splits the entries API URL the admin pasted into base URL, form id and query.
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
        $baseUrl = $this->params->has('base_url') ? $this->params->get('base_url') : null;
        if (is_string($baseUrl) && $baseUrl !== '') {
            $entriesPath .= '&excludeSource=' . rawurlencode($baseUrl);
        }
        $form = $this->remoteGet('api/forms/' . $this->config['remoteFormId']);
        $entries = $this->remoteGet($entriesPath);

        return [
            'form' => is_array($form) ? $form : [],
            'entries' => is_array($entries) ? $entries : [],
        ];
    }

    public function sourceAnswered(mixed $data): bool
    {
        return is_array($data) && !empty($data['form']) && !empty($data['entries']);
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
        echo empty($this->fileFieldKeys)
            ? 'Aucun champ fichier/image dans la correspondance : les fichiers ne seront pas importés.' . "\n"
            : 'Champs fichier/image (' . $this->filesMode() . ') : ' . implode(', ', $this->fileFieldKeys) . "\n";

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

            $mappedEntry['_remote_created_at'] = $remoteEntry['created_at'] ?? $remoteEntry['date_creation_fiche'] ?? null;
            $mappedEntry['_remote_updated_at'] = $remoteEntry['updated_at'] ?? $remoteEntry['date_maj_fiche'] ?? null;
            $mappedEntry['_remote_origin'] = is_string($remoteEntry['origin_url'] ?? null) ? $remoteEntry['origin_url'] : null;
            $mappedEntries[] = $mappedEntry;
        }

        return $mappedEntries;
    }

    public function syncFormModel(): void
    {
        if ($this->localFormExists) {
            if ($this->config['syncMode'] === 'source_of_truth') {
                $this->formManager->update($this->checkedLocalFormData());
                echo 'Le formulaire local a été synchronisé (miroir) avec le formulaire distant.' . "\n";
            } else {
                echo 'Le formulaire local existe déjà, aucune modification (mode allow_local).' . "\n";
            }

            return;
        }
        $this->formManager->create($this->checkedLocalFormData());
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
        $localIdsByOrigin = $this->localIdsByOrigin();
        foreach ($data as $mappedEntry) {
            $remoteUrl = $mappedEntry['_remote_url'];
            $remoteCreatedAt = $mappedEntry['_remote_created_at'] ?? null;
            $remoteUpdatedAt = $mappedEntry['_remote_updated_at'] ?? null;
            $remoteOrigin = $mappedEntry['_remote_origin'] ?? null;
            unset($mappedEntry['_remote_url'], $mappedEntry['_remote_created_at'], $mappedEntry['_remote_updated_at'], $mappedEntry['_remote_origin']);
            $title = $mappedEntry['title'] ?? $mappedEntry['bf_titre'] ?? $remoteUrl;

            try {
                $localId = $this->findLocalEntryByRemoteUrl($remoteUrl);
                $localEntry = empty($localId) ? null : $this->entryManager->getOne($localId);
                if (!empty($localId) && empty($localEntry)) {
                    $this->forgetSyncTriples($localId, false);
                    $localId = null;
                }

                if (empty($localId) || empty($localEntry)) {
                    $sameOriginId = $localIdsByOrigin[$remoteOrigin ?? $remoteUrl] ?? null;
                    if (!empty($sameOriginId) && !empty($this->entryManager->getOne($sameOriginId))) {
                        $seenLocalIds[] = $sameOriginId;
                        echo 'Entrée "' . $title . '" déjà présente ici par un autre chemin (fiche ' . $sameOriginId
                            . ') : ignorée, la source qui l\'a importée en premier la met à jour.' . "\n";
                        continue;
                    }
                    $newValues = $this->withRemoteDates($this->importEntryFiles($mappedEntry, null), $remoteCreatedAt, $remoteUpdatedAt);
                    $created = $this->entryManager->create($this->config['formId'], $newValues, false, $remoteUrl);
                    if (!empty($created['tag'])) {
                        $seenLocalIds[] = $created['tag'];
                        $localIdsByOrigin[$remoteOrigin ?? $remoteUrl] = $created['tag'];
                        $this->markSynced($created['tag'], date('Y-m-d H:i:s'));
                        $this->markOrigin($created['tag'], $remoteOrigin);
                        if (!$isSourceOfTruth) {
                            $stored = $this->entryManager->getOne($created['tag'], false, null, false) ?? [];
                            $this->setBase($created['tag'], EntryMerger::base($newValues, $stored, self::PASS_KEYS));
                        }
                        echo 'Entrée "' . $title . '" créée.' . "\n";
                    }
                    continue;
                }

                $seenLocalIds[] = $localId;
                $this->markOrigin($localId, $remoteOrigin);

                if ($isSourceOfTruth) {
                    $newValues = $this->withRemoteDates($this->importEntryFiles($mappedEntry, $localEntry), $remoteCreatedAt, $remoteUpdatedAt);
                    $changed = $this->changedFields($localEntry, $newValues);
                    if (empty($changed)) {
                        $unchangedCount++;
                        continue;
                    }
                    $this->entryManager->update($localId, $newValues, false, true);
                    echo 'Entrée "' . $title . '" mise à jour (miroir) : ' . implode(', ', $changed) . '.' . "\n";
                    continue;
                }

                $remoteValues = $this->withRemoteDates($this->importEntryFiles($mappedEntry, $localEntry), $remoteCreatedAt, $remoteUpdatedAt);
                $base = $this->getBase($localId);
                if ($base === null && $this->modifiedLocallySinceSync($localId, $localEntry)) {
                    $this->setBase($localId, EntryMerger::base($remoteValues, $remoteValues, self::PASS_KEYS));
                    echo 'Entrée "' . $title . '" modifiée localement, non synchronisée : '
                        . 'version de référence enregistrée, les prochaines synchros fusionneront.' . "\n";
                    continue;
                }
                $merge = EntryMerger::merge($localEntry, $remoteValues, $base ?? [], $this->config['conflictPolicy'], self::PASS_KEYS);
                foreach ($merge['conflicts'] as $key => $conflict) {
                    $kept = $this->config['conflictPolicy'] === EntryMerger::POLICY_REMOTE ? 'distante' : 'locale';
                    echo 'Entrée "' . $title . '" : conflit sur "' . $key . '", valeur ' . $kept . ' gardée '
                        . '(locale : "' . $this->excerpt($conflict['local']) . '", distante : "' . $this->excerpt($conflict['remote']) . '").' . "\n";
                }
                $changed = $this->changedFields($localEntry, $merge['values']);
                $stored = $localEntry;
                if (empty($changed)) {
                    $unchangedCount++;
                } else {
                    $this->entryManager->update($localId, $merge['values'], false, true);
                    $this->markSynced($localId, date('Y-m-d H:i:s'));
                    $stored = $this->entryManager->getOne($localId, false, null, false) ?? $merge['values'];
                    echo 'Entrée "' . $title . '" mise à jour : ' . implode(', ', $changed) . '.' . "\n";
                }
                $newBase = EntryMerger::storedInto($merge['base'], $stored, $merge['taken']);
                if ($newBase !== $base) {
                    $this->setBase($localId, $newBase);
                }
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
     * True for a field backed by a List, false for the "*fiche" variants that link to another form's entries.
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
     * Replaces each remote file name by a downloaded copy or the remote URL, keeping the local file when a download fails.
     *
     * @param array<string, mixed>      $mappedEntry
     * @param array<string, mixed>|null $localEntry
     *
     * @return array<string, mixed>
     */
    private function importEntryFiles(array $mappedEntry, ?array $localEntry): array
    {
        foreach ($this->fileFieldKeys as $key) {
            $value = $mappedEntry[$key] ?? null;

            if (empty($value) || !is_string($value) || filter_var($value, FILTER_VALIDATE_URL) !== false) {
                continue;
            }
            if (str_starts_with($value, 'defaultimage')) {
                $mappedEntry[$key] = '';
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
                $value,
                empty($this->cookie) ? [] : ['Cookie: ' . $this->cookie]
            );
            $currentValue = (string)($localEntry[$key] ?? '');
            if ($localFileName === '' && $currentValue !== '') {
                echo 'Fichier "' . $value . '" non récupéré : le fichier local "' . $currentValue . '" est conservé.' . "\n";
                $localFileName = $currentValue;
            }

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
     * @param array<mixed> $template a form's fields, stored or parsed from a remote raw template
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
            $propertyName = $field->getPropertyName();

            if (!empty($propertyName)) {
                $byProperty[$propertyName] = $field;
            }
        }

        return $byProperty;
    }

    /**
     * The local copy of the remote form, refused when a field name would not pass the form editor (GHSA-hgxv-w4r8-8877).
     *
     * @return array<string, mixed>
     */
    private function checkedLocalFormData(): array
    {
        $formData = $this->buildLocalFormData();
        $invalidNames = $this->formManager->invalidPropertyNames($this->formManager->getFromRawData($formData));
        if (!empty($invalidNames)) {
            throw new \Exception(_t('BAZ_FORM_INVALID_FIELD_NAMES', ['names' => htmlspecialchars(implode(', ', $invalidNames))]));
        }

        return $formData;
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

        if ($mirror) {
            $result = $this->writeList($localTag, $remoteTitle, $remoteNodes);
            echo $result === 'unchanged'
                ? 'Liste "' . $localTag . '" déjà à jour (miroir, ' . count($remoteNodes) . ' valeur(s)), non réécrite.' . "\n"
                : 'Liste "' . $localTag . '" synchronisée (miroir) avec ' . count($remoteNodes) . ' valeur(s).' . "\n";

            return;
        }

        $existingList = $this->listManager->isList($localTag) ? $this->listManager->getOne($localTag) : null;
        $byId = [];
        foreach (($existingList['nodes'] ?? []) as $node) {
            $byId[$node['id']] = $node;
        }
        foreach ($remoteNodes as $node) {
            $byId[$node['id']] = $node;
        }
        $mergedNodes = array_values($byId);
        $title = $existingList['title'] ?? $remoteTitle;
        $result = $this->writeList($localTag, $title, $mergedNodes);
        echo $result === 'unchanged'
            ? 'Liste "' . $localTag . '" déjà à jour (total local : ' . count($mergedNodes) . ' valeur(s)), non réécrite.' . "\n"
            : 'Liste "' . $localTag . '" fusionnée (total local : ' . count($mergedNodes) . ' valeur(s)).' . "\n";
    }

    /**
     * Stamps an entry with its remote creation date, and its modification date when 'keepRemoteUpdateDate' asks for it.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function withRemoteDates(array $values, ?string $createdAt, ?string $updatedAt): array
    {
        if (!empty($createdAt)) {
            $values['created_at'] = $createdAt;
        }
        if (!empty($updatedAt) && !empty($this->config['keepRemoteUpdateDate'])) {
            $values['updated_at'] = $updatedAt;
        }

        return $values;
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
            if (EntryMerger::comparable($value) !== EntryMerger::comparable($localEntry[$key] ?? null)) {
                $changed[] = $key;
            }
        }

        return $changed;
    }

    /**
     * The local entry already holding each origin URL, whichever source imported it first.
     *
     * @return array<string, string>
     */
    private function localIdsByOrigin(): array
    {
        $tripleStore = $this->getService(TripleStore::class);
        $originById = [];
        foreach ([TripleStore::ORIGIN_URL_URI, TripleStore::SOURCE_URL_URI] as $property) {
            foreach ($tripleStore->getMatching(null, $property, null, '=', '=', '=') as $triple) {
                $originById[(string)$triple['resource']] ??= (string)$triple['value'];
            }
        }
        $idsByOrigin = [];
        foreach ($originById as $id => $origin) {
            $idsByOrigin[$origin] ??= (string)$id;
        }

        return $idsByOrigin;
    }

    private function findLocalEntryByRemoteUrl(string $remoteUrl): ?string
    {
        $matches = $this->getService(TripleStore::class)->getMatching(null, TripleStore::SOURCE_URL_URI, $remoteUrl);

        return $matches[0]['resource'] ?? null;
    }

    private function baseProperty(): string
    {
        return 'sync_base_' . $this->source;
    }

    /**
     * The fingerprint of the entry as the last import wrote it, or null if none was recorded.
     *
     * @return array<string, array{r: string, l: string}>|null
     */
    private function getBase(string $localId): ?array
    {
        $json = $this->getService(TripleStore::class)->getOne($localId, $this->baseProperty(), '', '');
        $base = $json === null ? null : json_decode($json, true);

        return is_array($base) ? $base : null;
    }

    /**
     * @param array<string, array{r: string, l: string}> $base
     */
    private function setBase(string $localId, array $base): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $property = $this->baseProperty();
        $existing = $tripleStore->getOne($localId, $property, '', '');
        $json = (string)json_encode($base);
        if ($existing === null) {
            $tripleStore->create($localId, $property, $json, '', '');
        } elseif ($existing !== $json) {
            $tripleStore->update($localId, $property, $existing, $json, '', '');
        }
    }

    /**
     * Whether the entry was edited here after the last sync, for entries imported before merging existed.
     *
     * @param array<string, mixed> $localEntry
     */
    private function modifiedLocallySinceSync(string $localId, array $localEntry): bool
    {
        $lastSync = $this->getLastSyncTime($localId);
        $localUpdatedAt = $localEntry['updated_at'] ?? null;

        return $lastSync !== null && $localUpdatedAt !== null && $localUpdatedAt > $lastSync;
    }

    /** A value shortened for the sync log. */
    private function excerpt(mixed $value): string
    {
        $text = EntryMerger::comparable($value);

        return mb_strlen($text) > 80 ? mb_substr($text, 0, 80) . '…' : $text;
    }

    /** Records where the remote entry was first written, when it was itself imported, so the next wiki can pass it on. */
    private function markOrigin(string $localId, ?string $originUrl): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $existing = $tripleStore->getOne($localId, TripleStore::ORIGIN_URL_URI, '', '');
        if ($existing === $originUrl) {
            return;
        }
        if ($existing !== null) {
            $tripleStore->delete($localId, TripleStore::ORIGIN_URL_URI, null, '', '');
        }
        if ($originUrl !== null && $originUrl !== '') {
            $tripleStore->create($localId, TripleStore::ORIGIN_URL_URI, $originUrl, '', '');
        }
    }

    /** Drops the triples this source keeps about a local entry, its type too when the entry itself is gone. */
    private function forgetSyncTriples(string $localId, bool $withType): void
    {
        $tripleStore = $this->getService(TripleStore::class);
        $properties = [TripleStore::SOURCE_URL_URI, TripleStore::ORIGIN_URL_URI, $this->baseProperty()];
        if ($withType) {
            $properties = [TripleStore::TYPE_URI, ...$properties, $this->syncTimeProperty()];
        }
        foreach ($properties as $property) {
            $tripleStore->delete($localId, $property, null, '', '');
        }
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

    /** Signs in as the configured local admin so updates pass the write ACL; returns whether it did. */
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
        $previousUser = $authenticationService->getLoggedUser();
        $this->impersonationPreviousUser = is_array($previousUser) ? $previousUser : null;
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
            $this->forgetSyncTriples($localId, true);
            echo 'Entrée "' . $title . '" supprimée (disparue de la source).' . "\n";
        } catch (\Throwable $ex) {
            echo 'Erreur lors de la suppression de "' . $title . '" : ' . $ex->getMessage() . "\n";
        }
    }
}
