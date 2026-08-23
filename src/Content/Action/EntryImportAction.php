<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\CSVManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\RuntimeConfig;

class EntryImportAction extends YesWikiAction implements RegisteredAction
{
    /** `{{entryimport}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'entryimport';
    }

    public function formatArguments($arg)
    {
        $request = $this->getRequest();
        $vIDs = $request->get('form_id') ?? $request->get('id') ?? $arg['id'] ?? $arg['id'] ?? '';

        $vIDs = $this->getService(BazarListService::class)->getIDs($vIDs);

        $vServer = $request->get('server') ?? $arg['server'] ?? null;

        $post = $request->request;

        return [
            'id' => $vIDs,
            'server' => $vServer,
            'mode' => ($post->has('submit_file') && !empty($_FILES['fileimport']['name'])) ? 'submitfile' :
                ($post->has('importentries') ? 'importentries' : 'default'),
            'importentries' => $post->get('importentries'),
            'filesData' => $_FILES['fileimport'] ?? null,
            'bazar-import-option-detect-columns-on-headers' => !$this->formatBoolean($request->query->all() + $request->request->all(), false, 'bazar-import-option-not-detect-columns-on-headers'),
            'params' => array_merge(
                [BazarAction::URL_VIEW_PARAM => BazarAction::VIEW_IMPORT],
                $request->query->has('debug') ? ['debug' => 'yes'] : []
            ),
            'debug' => (bool)$this->getService(RuntimeConfig::class)->getValue('debug'),
        ];
    }

    /** @return string */
    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL())) {
            return $aclMessage;
        }

        if ($this->isWikiHibernated()) {
            return $this->getMessageWhenHibernated();
        }

        $csvManager = $this->getService(CSVManager::class);
        $entryController = $this->getService(EntryController::class);
        $bazarListService = $this->getService(BazarListService::class);

        $vRefresh = $this->arguments['refresh'] ?? $this->getRequest()->query->get('refresh', 'false');
        $vRefresh = ($vRefresh == 'true' || $vRefresh == '1') ? true : false;

        $vForms = $this->getService(FormManager::class)->getAll();

        switch ($this->arguments['mode']) {
            case 'submitfile':
                $vID = $bazarListService->getTheID($this->arguments['id']);

                if (empty($vID) || !empty($vID['isExternal'])) {
                    throw new \Exception('The specified ID for import should be local');
                }

                $vForm = $vForms[$vID['key']];

                if ($extracted = $csvManager->extractCSVfromCSVFile(
                    $this->arguments['id'],
                    $this->arguments['filesData'],
                    $this->arguments['bazar-import-option-detect-columns-on-headers'],
                    $vForm
                )) {
                    $extracted = array_map(function ($extract) use ($vForm, $entryController) {
                        $extract['displayData'] = $entryController->view($extract['entry'], '', false, null, $vForm);
                        $extract['json'] = json_encode($extract['entry']);

                        return $extract;
                    }, $extracted);
                }

                break;

            case 'importentries':
                $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
                $vID = $bazarListService->getTheID($this->arguments['id']);

                if (empty($vID) || !empty($vID['isExternal'])) {
                    throw new \Exception('The specified ID for import should be local');
                }

                $importedEntries = $csvManager->importEntry($this->arguments['importentries'], $vID['id']);
                break;

            case 'default':
            default:
                $vID = $bazarListService->getTheID($this->arguments['id'], false);

                if (!empty($vID)) {
                    $vForm = $vForms[$vID['key']];

                    $csv_template = $csvManager->getCSVfromFormId($vID['id'], [], ['fakeMode' => true]);
                }
                break;
        }

        if (!empty($vID)) {
            $vFilename = $csvManager->buildExportFilename($vID);
        }

        return $this->render('@core/bazar-import.twig', [
            'id' => $vID['id'] ?? '',
            'server' => $this->arguments['server'],
            'forms' => $vForms,
            'params' => $this->arguments['params'],
            'filename' => $vFilename ?? '',
            'csv' => isset($csv_template) ? $csvManager->arrayToCSVToDisplay($csv_template) : null,
            'selectedForm' => $vForm ?? null,
            'importedEntries' => $importedEntries ?? null,
            'extracted' => $extracted ?? null,
            'mode' => $this->arguments['mode'],
            'optionNotDetectColumnsOnHeadersChecked' => !$this->arguments['bazar-import-option-detect-columns-on-headers'],
            'debug' => $this->arguments['debug'],
        ]);
    }
}
