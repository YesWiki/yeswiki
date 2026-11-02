<?php

namespace YesWiki\Import\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Import\Service\ImporterManager;

class ImportApiController extends YesWikiController
{
    /** Sync every configured data source, for a webhook or an external cron. */
    #[Route('/api/import/sync', methods: ['GET'], options: ['acl' => ['public']])]
    public function sync(): ApiResponse
    {
        $params = $this->getService(ParameterBagInterface::class);
        $expectedSecret = $params->has('sync_secret') ? $params->get('sync_secret') : '';
        if (!$this->sharesSecret(is_scalar($expectedSecret) ? (string)$expectedSecret : '', (string)$this->getRequest()->headers->get('secret', ''))) {
            return new ApiResponse(['error' => 'Unauthorized'], 401);
        }

        $importerManager = $this->getService(ImporterManager::class);
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];

        $results = [];
        foreach ((is_array($dataSources) ? $dataSources : []) as $source => $sourceOptions) {
            // A failure is the source's answer, and the others go on.
            $results[$source] = $importerManager->syncByHand((string)$source, $sourceOptions)['result'];
        }

        return new ApiResponse($results);
    }

    /**
     * Admin-only endpoint backing the live field-mapping table on `{{adminimporters}}`: given the in-progress add/edit form fields (posted with the same "{key}{importer}" convention the page saves with) and a local formId, returns the remote/local field lists needed to build the mapping table without a full page submit and reload.
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
