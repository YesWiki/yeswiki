<?php

namespace YesWiki\Content\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ImporterManager;
use YesWiki\Content\Service\SyncScheduler;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;

class ImportApiController extends YesWikiController
{
    /**
     * Sync every configured data source, for a webhook or an external cron.
     *
     * Guarded by a shared secret rather than by the wiki's ACLs: the caller is a machine with
     * no session, and the alternative -- an admin account whose password lives in a crontab --
     * is a worse thing to leak. Empty `sync_secret` (the default) leaves the route refusing
     * everything, so a wiki that never configured one is not exposed by having upgraded.
     */
    #[Route('/api/import/sync', methods: ['GET'], options: ['acl' => ['public']])]
    public function sync(): ApiResponse
    {
        $params = $this->getService(ParameterBagInterface::class);
        $expectedSecret = $params->has('sync_secret') ? $params->get('sync_secret') : null;
        $providedSecret = $this->getRequest()->headers->get('secret');

        if (empty($expectedSecret) || !is_scalar($expectedSecret) || empty($providedSecret)
            || !hash_equals((string)$expectedSecret, (string)$providedSecret)) {
            return new ApiResponse(['error' => 'Unauthorized'], 401);
        }

        $importerManager = $this->getService(ImporterManager::class);
        $scheduler = $this->getService(SyncScheduler::class);
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];

        $results = [];
        foreach ((is_array($dataSources) ? $dataSources : []) as $source => $sourceOptions) {
            ob_start();
            $results[$source] = $importerManager->syncSource((string)$source, $sourceOptions);
            // the per-entry detail belongs in the source's log, not in this json answer
            $scheduler->recordRun((string)$source, trim(ob_get_clean() . "\n" . $results[$source]));
        }

        return new ApiResponse($results);
    }

    /**
     * Admin-only endpoint backing the live field-mapping table on `{{adminimporters}}`: given
     * the in-progress add/edit form fields (posted with the same "{key}{importer}" convention
     * the page saves with) and a local formId, returns the remote/local field lists needed to
     * build the mapping table without a full page submit and reload.
     */
    #[Route('/api/import/mapping-fields', methods: ['POST'], options: ['acl' => ['public']])]
    public function mappingFields(): ApiResponse
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return new ApiResponse(['error' => 'Unauthorized'], 403);
        }

        $request = $this->getRequest();
        $importer = (string)$request->request->get('importer', '');
        $formId = (string)$request->request->get('formId', '');

        if (empty($importer) || empty($formId) || $formId === 'new') {
            return new ApiResponse(['fieldMapping' => null]);
        }

        $importerManager = $this->getService(ImporterManager::class);
        if (empty($importerManager->getAvailableImporters()[$importer])) {
            return new ApiResponse(['fieldMapping' => null]);
        }

        $importerFields = [$importer => $importerManager->getAdminFieldsFor($importer)];
        $sourceOptions = $importerManager->collectSourceOptionsFromInput($importer, $importerFields, $request->request->all());

        $localForm = $this->getService(FormManager::class)->getOne($formId);
        $fieldMapping = $localForm ? $importerManager->getFieldMapping($importer, $sourceOptions, $localForm) : null;

        return new ApiResponse(['fieldMapping' => $fieldMapping]);
    }
}
