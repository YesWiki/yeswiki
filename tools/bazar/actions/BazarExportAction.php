<?php

use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiAction;

class BazarExportAction extends YesWikiAction
{
    private $CSVManager;
    private $formManager;
    private $bazarListService;

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $get = $request->query;
        $vIDs = $request->get('id_typeannonce') ?? $request->get('id') ?? $arg['idtypeannonce'] ?? $arg['id'] ?? '';

        // get services
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vIDs = $this->bazarListService->getIDs($vIDs);

        return [
            'id' => $vIDs ?? null,
            'keywords' => $this->getService(SearchManager::class)->aggregateKeywords($get->get('q'), $get->get('keywords')),
            'query' => $get->get('query'),
            'bazar-export-option-keys-instead-of-values' => $this->formatBoolean($get->all() + $request->request->all(), false, 'bazar-export-option-keys-instead-of-values'),
            'params' => array_merge(
                [BAZ_VARIABLE_VOIR => BAZ_VOIR_EXPORTER],
                $get->has('debug') ? ['debug' => 'yes'] : []
            ),
        ];
    }

    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL(false))) {
            return $aclMessage;
        }

        // get services
        $this->CSVManager = $this->getService(CSVManager::class);
        $this->formManager = $this->getService(FormManager::class);
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vForms = $this->formManager->getAll();

        // get CSV

        $csvraw = null;

        $vTheID = $this->bazarListService->getTheID($this->arguments['id'], false);

        if ($vTheID) {
            $vID = $vTheID['id'];

            $vRefresh = $this->arguments['refresh'] ?? $this->getRequest()->query->get('refresh', 'false');
            $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

            $vSelectedForm = $vForms[$vID];

            $csv_raw = $this->CSVManager->getCSVfromFormId(
                $vID,
                [
                    'query' => $this->arguments['query'],
                    'keywords' => $this->arguments['keywords'],
                ],
                [
                    'fakeMode' => false, // No fake CSV
                    'keysInsteadOfValues' => $this->arguments['bazar-export-option-keys-instead-of-values'],
                ]
            );

            $vFilename = $this->CSVManager->buildExportFilename($vTheID);
        } else {
            // get Forms
            $vSelectedForm = null;
        }

        return $this->render('@bazar/bazar-export.twig', [
            'id' => $vID ?? null,
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'filename' => $vFilename ?? null,
            'selectedForm' => $vSelectedForm,
            'csv' => !empty($csv_raw) ? $this->CSVManager->arrayToCSVToDisplay($csv_raw) : null,
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0,
            'optionKeysInsteadOfValuesChecked' => $this->arguments['bazar-export-option-keys-instead-of-values'],
        ]);
    }
}
