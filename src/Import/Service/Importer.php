<?php

namespace YesWiki\Import\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;

abstract class Importer
{
    protected ParameterBagInterface $params;
    protected ContainerInterface $services;
    protected EntryManager $entryManager;
    protected ImporterManager $importerManager;
    protected FormManager $formManager;
    protected ListManager $listManager;
    /**
     * @var array<string, mixed> this importer's own data source options
     */
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

    /** Everything this source has to offer, in whatever shape it comes in. */
    public function getData(): mixed
    {
        return null;
    }

    /** Turn what getData() fetched into what syncData() writes. */
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
     * Declare the config fields this importer needs, so AdminImportersAction (from this extension) can render/save them for the admin, even when the importer itself lives in another extension.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getAdminFields(): array
    {
        return [];
    }

    /** Whether this importer creates/updates Bazar entries and therefore needs a target Bazar formId. */
    public static function needsBazarForm(): bool
    {
        return true;
    }

    /**
     * Declare the fixed set of fields this importer writes into an entry, so AdminImportersAction can offer a field-mapping table when the admin points it at an already-existing Bazar form instead of letting it create its own.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function getOwnFields(): array
    {
        return [];
    }

    /**
     * Whether this importer can offer a field-mapping table built from a remote form fetched live (ImporterManager::getFieldMapping()), rather than from a fixed getOwnFields() list.
     */
    public static function hasRemoteFieldMapping(): bool
    {
        return false;
    }

    /** Normalize a raw admin-posted field value before it's stored in config, e.g. */
    public static function normalizeAdminFieldValue(string $key, mixed $value): mixed
    {
        return $value;
    }

    /**
     * Normalize the whole set of admin-posted options before it's stored in config, for importers whose config keys can't be derived one field at a time (e.g.
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
     * Reverse of normalizeAdminOptions(): rebuild, from the stored config, the values to prefill the admin form with when editing a source, so that re-saving an unmodified source is a no-op.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function denormalizeAdminOptions(array $options): array
    {
        return $options;
    }

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
     * Rename this importer's own field keys to the local form's field keys, per config['fieldsMapping'] (built from the admin's field-mapping table, itself populated from getOwnFields()).
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
