<?php

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\CSVManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\YesWikiAction;

class BazarImportAction extends YesWikiAction
{
    private $CSVManager;
    private $formManager;
    private $entryController;

    public function formatArguments($arg)
    {
        $id = (!empty($_REQUEST['id'])) ? $_REQUEST['id'] : ($_REQUEST['id_typeannonce'] ?? ($arg['id'] ?? ''));

        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        return [
            'id' => $id,
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

        // get Forms
        $forms = $this->formManager->getAll();

        // switch to right method
        switch ($this->arguments['mode']) {
            case 'submitfile':
                if ($extracted = $this->CSVManager->extractCSVfromCSVFile(
                    $this->arguments['id'],
                    $this->arguments['filesData'],
                    $this->arguments['bazar-import-option-detect-columns-on-headers']
                )) {
                    // append displayData
                    $extracted = array_map(function ($extract) {
                        $extract['displayData'] = $this->entryController->view($extract['entry'], '', 0);
                        $extract['base64'] = base64_encode(serialize($extract['entry']));

                        return $extract;
                    }, $extracted);
                }
                break;

            case 'importentries':
                $importedEntries = $this->CSVManager->importEntry($this->arguments['importentries'], $this->arguments['id']);
                break;

            case 'default':
            default:
                // get csv_template
                $csv_template = $this->CSVManager->getCSVfromFormId($this->arguments['id'], null, true);
                break;
        }

        return $this->render('@bazar/bazar-import.twig', [
            'id' => $this->arguments['id'],
            'forms' => $forms,
            'params' => $this->arguments['params'],
            'csv' => isset($csv_template) ? $this->CSVManager->arrayToCSVToDisplay($csv_template) : null,
            'selectedForm' => $this->formManager->getOne($this->arguments['id']),
            'importedEntries' => $importedEntries ?? null,
            'extracted' => $extracted ?? null,
            'mode' => $this->arguments['mode'],
            'optionNotDetectColumnsOnHeadersChecked' => !$this->arguments['bazar-import-option-detect-columns-on-headers'],
            'debug' => $this->arguments['debug'],
        ]);
    }
}
