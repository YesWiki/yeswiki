<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class Importer
{
    protected ParameterBagInterface $params;
    protected ContainerInterface $services;
    protected EntryManager $entryManager;
    protected ImporterManager $importerManager;
    protected FormManager $formManager;
    protected ListManager $listManager;
    /** @var array<string, mixed> this importer's own data source options */
    protected array $config = [];

    public function __construct(
        ParameterBagInterface $params,
        ContainerInterface $services,
        EntryManager $entryManager,
        ImporterManager $importerManager,
        FormManager $formManager,
        ListManager $listManager
    ) {
        $this->params = $params;
        $this->services = $services;
        $this->entryManager = $entryManager;
        $this->importerManager = $importerManager;
        $this->formManager = $formManager;
        $this->listManager = $listManager;
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];
        $this->config = $this->checkConfig(is_array($dataSources) ? $dataSources : []);
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
        return $config;
    }

    public function authenticate(): void
    {
    }

    /**
     * Everything this source has to offer, in whatever shape it comes in.
     */
    public function getData(): mixed
    {
        return null;
    }

    /**
     * Turn what getData() fetched into what syncData() writes. Declared here because
     * ImporterManager drives every importer through getData() -> mapData() -> syncData();
     * an importer with nothing to map returns $data unchanged.
     */
    public function mapData(mixed $data): mixed
    {
        return $data;
    }

    public function syncFormModel(): void
    {
    }

    /**
     * @param array<mixed> $data
     */
    public function syncData(array $data): void
    {
    }

    /**
     * Declare the config fields this importer needs, so AdminImportersAction (from this
     * extension) can render/save them for the admin, even when the importer itself lives
     * in another extension.
     * Each entry: 'key' => ['type' => 'text'|'url'|'password'|'checkbox'|'select'|'number',
     * 'required' => bool, 'options' => [value => labelKey] (select only),
     * 'label' => labelKey (optional, defaults to "IMPORTER_FIELD_{KEY}"),
     * 'help' => labelKey (optional hint displayed under the field)]
     * A key prefixed "auth_" is stored nested as config['auth'][...] (e.g. "auth_user" -> auth.user).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAdminFields(): array
    {
        return [];
    }

    /**
     * Whether this importer creates/updates Bazar entries and therefore needs a target Bazar formId.
     */
    public static function needsBazarForm(): bool
    {
        return true;
    }

    /**
     * Declare the fixed set of fields this importer writes into an entry, so
     * AdminImportersAction can offer a field-mapping table when the admin points it at an
     * already-existing Bazar form instead of letting it create its own. Only list fields safe
     * to rename: an importer must not include here any key it also relies on programmatically
     * after mapData() (e.g. a dedup/identity key), since applyFieldsMapping() would rename it
     * away. Return [] (the default) if this importer has no fixed field list to offer (e.g. it
     * fetches an arbitrary remote form's fields instead, like YesWikiToYesWikiImporter).
     * Each entry: ['key' => propertyName, 'label' => humanLabel].
     *
     * @return list<array{key: string, label: string}>
     */
    public static function getOwnFields(): array
    {
        return [];
    }

    /**
     * Whether this importer can offer a field-mapping table built from a remote form fetched
     * live (ImporterManager::getFieldMapping()), rather than from a fixed getOwnFields() list.
     */
    public static function hasRemoteFieldMapping(): bool
    {
        return false;
    }

    /**
     * Normalize a raw admin-posted field value before it's stored in config, e.g. to recover
     * from a common paste mistake (a full API url pasted where only the wiki's base url is
     * expected). Default: no-op. Override for fields whose expected shape can be sanitized.
     */
    public static function normalizeAdminFieldValue(string $key, mixed $value): mixed
    {
        return $value;
    }

    /**
     * Normalize the whole set of admin-posted options before it's stored in config, for
     * importers whose config keys can't be derived one field at a time (e.g. a single pasted
     * url that carries several config values at once). Applied by
     * ImporterManager::collectSourceOptionsFromInput(), so both the admin page and the
     * field-mapping AJAX endpoint see the same normalized options. Default: no-op.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function normalizeAdminOptions(array $options): array
    {
        return $options;
    }

    /**
     * Reverse of normalizeAdminOptions(): rebuild, from the stored config, the values to
     * prefill the admin form with when editing a source, so that re-saving an unmodified
     * source is a no-op. Default: no-op.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function denormalizeAdminOptions(array $options): array
    {
        return $options;
    }

    // HELPERS

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected function getService($class)
    {
        return $this->services->get($class);
    }

    /**
     * Rename this importer's own field keys to the local form's field keys, per
     * config['fieldsMapping'] (built from the admin's field-mapping table, itself populated
     * from getOwnFields()). A no-op when no mapping was configured (own form, field-for-field).
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    protected function applyFieldsMapping(array $entry): array
    {
        $mapping = $this->config['fieldsMapping'] ?? [];
        if (empty($mapping)) {
            return $entry;
        }
        $mapped = [];
        foreach ($entry as $key => $value) {
            $mapped[$mapping[$key] ?? $key] = $value;
        }

        return $mapped;
    }
}
