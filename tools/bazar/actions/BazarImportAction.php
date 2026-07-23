<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\BazarListService;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\ExternalBazarService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiAction;

class BazarImportAction extends YesWikiAction
{
    private $CSVManager;
    private $formManager;
    private $entryController;
    private $bazarListService;

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $vIDs = $request->get('id_typeannonce') ?? $request->get('id') ?? $arg['idtypeannonce'] ?? $arg['id'] ?? '';

        if (!$this->bazarListService) {
            $this->bazarListService = $this->getService(BazarListService::class);
        }

        $vIDs = $this->bazarListService->getIDs($vIDs);

        $vServer = $request->get('server') ?? $arg['server'] ?? null;

        $post = $request->request;
        return [
            'id' => $vIDs,
            'server' => $vServer,
            'mode' => ($post->has('submit_file') && !empty($_FILES['fileimport']['name'])) ? 'submitfile' :
                ($post->has('importfiche') ? 'importentries' : 'default'),
            'importentries' => $post->get('importfiche'),
            'filesData' => $_FILES['fileimport'] ?? null,
            'bazar-import-option-detect-columns-on-headers' => !$this->formatBoolean($request->query->all() + $request->request->all(), false, 'bazar-import-option-not-detect-columns-on-headers'),
            'params' => array_merge(
                [BAZ_VARIABLE_VOIR => BAZ_VOIR_IMPORTER],
                $request->query->has('debug') ? ['debug' => 'yes'] : []
            ),
            'debug' => (bool)$this->wiki->GetConfigValue('debug'),
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

        $vRefresh = $this->arguments['refresh'] ?? $this->getRequest()->query->get('refresh', 'false');
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
                $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'csrf-token', false);
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

        if (!empty($vID)) {
            $vFilename = $this->CSVManager->buildExportFilename($vID);
        }

        return $this->render('@bazar/bazar-import.twig', [
            'id' => $vID['id'] ?? '',
            'server' => $this->arguments['server'],
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'filename' => $vFilename ?? '',
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
