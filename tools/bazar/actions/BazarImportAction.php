<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;

class BazarImportAction extends YesWikiAction
{
    private $CSVManager;
    private $formManager;
    private $entryController;
    private $bazarListService;

    public function formatArguments($arg)
    {
        $vIDs = $_REQUEST['id_typeannonce'] ?? $_REQUEST['id'] ?? $arg['idtypeannonce'] ?? $arg['id'] ?? '';

        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vIDs = $this->bazarListService->getIDs($vIDs);

        $vServer = $_REQUEST['server'] ?? $arg['server'] ?? null;

        return [
            'id' => $vIDs,
            'server' => $vServer,
            'mode' => (isset($_POST['submit_file']) && !empty($_FILES['fileimport']['name'])) ? 'submitfile' :
                (isset($_POST['importfiche']) ? 'importentries' : 'default'),
            'importentries' => $_POST['importfiche'] ?? null,
            'filesData' => $_FILES['fileimport'] ?? null,
            'bazar-import-option-detect-columns-on-headers' => !$this->formatBoolean($_REQUEST, false, 'bazar-import-option-not-detect-columns-on-headers'),
            'params' => array_merge(
                [BAZ_VARIABLE_VOIR => BAZ_VOIR_IMPORTER],
                isset($_GET['debug']) ? ['debug' => 'yes'] : []
            ),
            'debug' => ($this->wiki->GetConfigValue('debug') == 'yes'),
        ];
    }

    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL())) {
            return $aclMessage;
        }

        if ($this->isWikiHibernated()) {
            return $this->getMessageWhenHibernated();
        }

        // get services
        $this->CSVManager = $this->getService(CSVManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->entryController = $this->getService(EntryController::class);
        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vRefresh = $this->arguments['refresh'] ?? $_GET['refresh'] ?? 'false';
        $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

        // get Forms

        if (empty($this->arguments['server'])) {
            $vForms = $this->formManager->getAll();
        } else {
            $vForms = $this->getService(ExternalBazarService::class)->getForms($this->arguments['server']);
        }

        // switch to right method
        switch ($this->arguments['mode']) {
            case 'submitfile':
                $vID = $this->bazarListService->getTheID($this->arguments['id']);

                if ($vID['isExternal']) {
                    throw \Exception('The specified ID for import should be local');

                    return 'The specified ID for import should be local';
                }

                $vForm = $vForms[$vID['key']];

                if ($extracted = $this->CSVManager->extractCSVfromCSVFile(
                    $this->arguments['id'],
                    $this->arguments['filesData'],
                    $this->arguments['bazar-import-option-detect-columns-on-headers'],
                    $vForm
                )) {
                    // append displayData
                    $extracted = array_map(function ($extract) use ($vForm) {
                        $extract['displayData'] = $this->entryController->view($extract['entry'], '', 0, null, $vForm);
                        $extract['json'] = json_encode($extract['entry']);

                        return $extract;
                    }, $extracted);
                }

                break;

            case 'importentries':
                $vID = $this->bazarListService->getTheID($this->arguments['id']);

                if ($vID['isExternal']) {
                    throw \Exception('The specified ID for import should be local');

                    return 'The specified ID for import should be local';
                }

                $importedEntries = $this->CSVManager->importEntry($this->arguments['importentries'], $vID['id']);
                break;

            case 'default':
            default:
                $vID = $this->bazarListService->getTheID($this->arguments['id'], false);

                if (!empty($vID)) {
                    $vForm = $vForms[$vID['key']];

                    // get csv_template
                    $csv_template = $this->CSVManager->getCSVfromFormId($vID['id'], [], ['fakeMode' => true]);
                }
                break;
        }

        return $this->render('@bazar/bazar-import.twig', [
            'id' => $vID['id'] ?? '',
            'server' => $this->arguments['server'],
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'csv' => isset($csv_template) ? $this->CSVManager->arrayToCSVToDisplay($csv_template) : null,
            'selectedForm' => $vForm ?? null,
            'importedEntries' => $importedEntries ?? null,
            'extracted' => $extracted ?? null,
            'mode' => $this->arguments['mode'],
            'optionNotDetectColumnsOnHeadersChecked' => !$this->arguments['bazar-import-option-detect-columns-on-headers'],
            'debug' => $this->arguments['debug'],
        ]);
    }
}
