<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ImporterManager;
use YesWiki\Content\Service\SyncScheduler;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{adminimporters}}`: declare where this wiki imports Content from, and sync it.
 *
 * The page writes data sources straight into the configuration file, so every importer --
 * core's and any an extension adds -- is configured from one place instead of by hand.
 */
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
        // each importer class (from this extension or any other) declares its own admin
        // fields via Importer::getAdminFields()/needsBazarForm(), so this action stays
        // extension-agnostic instead of hardcoding a field list per importer name
        $importerFields = [];
        $importersWithoutForm = [];
        // an importer can offer field-mapping either from a fixed field list (getOwnFields(),
        // e.g. Rss/Imap) or by fetching an arbitrary remote form's fields live via the
        // mapping-fields AJAX endpoint (hasRemoteFieldMapping(), e.g. YesWikiToYesWiki)
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

        // the admin no longer types a raw formId: "new" means "create a form, pick its id now"
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
            // ArrayAccess rather than the magic property: ConfigurationFile declares neither,
            // and only one of the two is a shape static analysis can follow
            $config['dataSources'] = $dataSources;
            $config->write();
            $message = _t('IMPORTER_SOURCE_DELETED');
        } elseif (!empty($syncSource) && isset($dataSources[$syncSource])) {
            $syncedSourceId = $syncSource;
            // a sync can take a while (remote wikis, large forms/entries/lists); this is an
            // admin-triggered, one-off action so the regular script execution time limit
            // would otherwise cut it short
            set_time_limit(0);
            ob_start();
            $result = $importerManager->syncSource($syncedSourceId, $dataSources[$syncedSourceId]);
            $syncOutput = trim(ob_get_clean() . "\n" . $result);
            // a hand-triggered sync counts as this source's last sync too, so the table below
            // and the automatic scheduler agree on when it last ran
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

        // both the sources table and the edit form show what was typed in, not how it ended up
        // stored (an importer may split one typed value into several config keys)
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
     * When each source last synced and what it did -- whether that was the automatic sync
     * (config 'syncOnMaintenance'), the console command, or the button on this page. A sync
     * nobody watched must not be invisible: an admin needs to see that it ran, and what of.
     */
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
     * Turn the stored sources back into what the admin form was filled with, so that editing
     * then re-saving a source unchanged doesn't alter it (an importer may store its config
     * differently from how it's typed in, see Importer::normalizeAdminOptions()).
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
     * Derive a stable id from the source's type and url, so re-saving the same source
     * (same importer + url) keeps producing the same id instead of a random one. Two sources
     * can share a wiki's url while importing different things from it, hence the remote
     * form/list they target being part of the id too.
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
