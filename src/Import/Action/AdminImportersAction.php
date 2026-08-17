<?php

namespace YesWiki\Import\Action;

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Import\Service\ImporterManager;
use YesWiki\Import\Service\SyncScheduler;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{adminimporters}}`: declare where this wiki imports Content from, and sync it. */
class AdminImportersAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'adminimporters';
    }

    public function run(): string
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $configFile = ConfigurationFileProvider::getConfigFileFromEnv();
        if (!is_writable($configFile)) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ERROR_NO_ACCESS') . ' ' . _t('FILE_WRITE_PROTECTED'),
            ]);
        }

        $importerManager = $this->getService(ImporterManager::class);
        $importers = $importerManager->getAvailableImporters();
        $formManager = $this->getService(FormManager::class);

        $importerFields = [];
        $importersWithoutForm = [];

        $importersWithFieldMapping = [];
        foreach ($importers as $shortName => $className) {
            $importerFields[$shortName] = $importerManager->getAdminFieldsFor($shortName);
            if (!$className::needsBazarForm()) {
                $importersWithoutForm[] = $shortName;
            }
            if (!empty($className::getOwnFields()) || $className::hasRemoteFieldMapping()) {
                $importersWithFieldMapping[] = $shortName;
            }
        }

        $config = $this->getService(ConfigurationService::class)->getConfiguration($configFile);
        $config->load();
        $dataSources = isset($config->dataSources) && is_array($config->dataSources) ? $config->dataSources : [];

        $request = $this->getRequest();

        if ($request->request->get('formId', '') === 'new') {
            $request->request->set('formId', (string)$formManager->findNewId());
        }

        $message = null;
        $syncOutput = null;
        $syncedSourceId = null;

        $delete = (string)$request->request->get('delete', '');
        $syncSource = (string)$request->request->get('syncSource', '');
        $importer = (string)$request->request->get('importer', '');

        if (!empty($delete) && isset($dataSources[$delete])) {
            unset($dataSources[$delete]);

            $config['dataSources'] = $dataSources;
            $config->write();
            $message = _t('IMPORTER_SOURCE_DELETED');
        } elseif (!empty($syncSource) && isset($dataSources[$syncSource])) {
            $syncedSourceId = $syncSource;

            set_time_limit(0);
            ob_start();
            $result = $importerManager->syncSource($syncedSourceId, $dataSources[$syncedSourceId]);
            $syncOutput = trim(ob_get_clean() . "\n" . $result);

            $this->getService(SyncScheduler::class)->recordRun($syncedSourceId, $syncOutput);
        } elseif (!empty($importer)) {
            $sourceOptions = $importerManager->collectSourceOptionsFromInput($importer, $importerFields, $request->request->all());
            $id = (string)($request->request->get('id') ?: $this->generateId($importer, $sourceOptions));
            $fieldsMapping = array_filter($request->request->all('fieldsMapping'));
            if (!empty($fieldsMapping)) {
                $sourceOptions['fieldsMapping'] = $fieldsMapping;
            }
            $dataSources[$id] = $sourceOptions;
            $config['dataSources'] = $dataSources;
            $config->write();
            $message = _t('IMPORTER_SOURCE_SAVED');
        }

        $editableDataSources = $this->editableDataSources($dataSources, $importers);

        return $this->render('@core/admin-importers.twig', [
            'currentUrl' => $this->getService(UrlFormatter::class)->href(),
            'lastSync' => $this->lastSyncStatus($dataSources),
            'importers' => $importers,
            'importerFields' => $importerFields,
            'importersWithoutForm' => $importersWithoutForm,
            'importersWithFieldMapping' => $importersWithFieldMapping,
            'forms' => $formManager->getAll(),
            'dataSources' => $editableDataSources,
            'dataSourcesJson' => json_encode($editableDataSources, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
            'message' => $message,
            'syncOutput' => $syncOutput,
            'syncedSourceId' => $syncedSourceId,
        ]);
    }

    /**
     * @param array<string, array<string, mixed>> $dataSources
     *
     * @return array<string, array{enabled: bool, last: array{time: int, output: string}|null}>
     */
    private function lastSyncStatus(array $dataSources): array
    {
        $scheduler = $this->getService(SyncScheduler::class);
        $status = [];
        foreach ($dataSources as $id => $source) {
            $status[$id] = [
                'enabled' => !empty($source['syncOnMaintenance']),
                'last' => $scheduler->getLastSync((string)$id),
            ];
        }

        return $status;
    }

    /**
     * Turn the stored sources back into what the admin form was filled with, so that editing then re-saving a source unchanged doesn't alter it (an importer may store its config differently from how it's typed in, see Importer::normalizeAdminOptions()).
     *
     * @param array<string, array<string, mixed>> $dataSources
     * @param array<string, class-string>         $importers
     *
     * @return array<string, array<string, mixed>>
     */
    private function editableDataSources(array $dataSources, array $importers): array
    {
        $editable = [];
        foreach ($dataSources as $id => $source) {
            $className = $importers[$source['importer'] ?? ''] ?? null;
            $editable[$id] = $className !== null ? $className::denormalizeAdminOptions($source) : $source;
        }

        return $editable;
    }

    /**
     * Derive a stable id from the source's type and url, so re-saving the same source (same importer + url) keeps producing the same id instead of a random one.
     *
     * @param array<string, mixed> $sourceOptions
     */
    public function generateId(string $importer, array $sourceOptions): string
    {
        $url = $sourceOptions['url'] ?? $sourceOptions['imap_server_and_folder'] ?? '';
        $target = $sourceOptions['remoteFormId'] ?? $sourceOptions['listId'] ?? '';

        return strtolower($importer) . '_' . substr(sha1($importer . '|' . $url . '|' . $target), 0, 12);
    }
}
