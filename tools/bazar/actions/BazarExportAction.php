<?php

use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\YesWikiAction;

class BazarExportAction extends YesWikiAction
{
    private $CSVManager;
    private $formManager;

    public function formatArguments($arg)
    {
        $id = (!empty($_REQUEST['id'])) ? $_REQUEST['id'] : ($_REQUEST['id_typeannonce'] ?? ($arg['id'] ?? ''));

        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        return [
            'id' => $id,
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

        // get Forms
        $forms = $this->formManager->getAll();

        // get CSV
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

        return $this->render('@bazar/bazar-export.twig', [
            'id' => $this->arguments['id'],
            'forms' => $forms,
            'params' => $this->arguments['params'],
            'selectedForm' => $this->formManager->getOne($this->arguments['id']),
            'csv' => $this->CSVManager->arrayToCSVToDisplay($csv_raw),
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0,
            'optionKeysInsteadOfValuesChecked' => $this->arguments['bazar-export-option-keys-instead-of-values'],
        ]);
    }
}
