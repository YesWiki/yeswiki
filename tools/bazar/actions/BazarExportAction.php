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
        $vIDs = $_REQUEST['id_typeannonce'] ?? $_REQUEST['id'] ?? $arg['idtypeannonce'] ?? $arg['id'] ?? '';

        // get services
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vIDs = $this->bazarListService->getIDs($vIDs);

        return [
            'id' => $vIDs,
            'keywords' => $this->getService(SearchManager::class)->aggregateKeywords($_GET['q'] ?? null, $_GET['keywords'] ?? null), // chaine de recherche
            'query' => $_GET['query'] ?? null,
            'bazar-export-option-keys-instead-of-values' => $this->formatBoolean($_REQUEST, false, 'bazar-export-option-keys-instead-of-values'),
            'params' => array_merge(
                [BAZ_VARIABLE_VOIR => BAZ_VOIR_EXPORTER],
                isset($_GET['debug']) ? ['debug' => 'yes'] : []
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

        // get CSV

        if (count($this->arguments['id']['locals']) + count($this->arguments['id']['externals']) == 1) {
            $forms = $this->bazarListService->getForms(['idtypeannonce' => $this->arguments['id']]);

            $vRefresh = $this->arguments['refresh'] ?? $_GET['refresh'] ?? 'false';
            $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

            // get Forms
            $vForms = $this->bazarListService->getForms(['idtypeannonce' => $this->arguments['id'], 'refresh' => $vRefresh]);

            $vSelectedForm = reset($forms);

            $csv_raw = $this->CSVManager->getCSVfromFormId(
                $this->arguments['id'],
                [
                    'query' => $this->arguments['query'],
                    'keywords' => $this->arguments['keywords'],
                ],
                [
                    'fakeMode' => false, // No fake CSV
                    'keysInsteadOfValues' => $this->arguments['bazar-export-option-keys-instead-of-values'],
                ]
            );
            $forms = [$vSelectedForm];
        } else {
            // get Forms
            $forms = $this->formManager->getAll();
            $vSelectedForm = null;
        }

        return $this->render('@bazar/bazar-export.twig', [
            'id' => $this->arguments['id'],
            'forms' => $forms,
            'params' => $this->arguments['params'],
            'selectedForm' => $vSelectedForm,
            'csv' => $this->CSVManager->arrayToCSVToDisplay($csv_raw),
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0,
            'optionKeysInsteadOfValuesChecked' => $this->arguments['bazar-export-option-keys-instead-of-values'],
        ]);
    }
}
