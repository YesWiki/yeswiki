<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ImportManager;

class BazarImportAction extends YesWikiAction
{
    private $importManager;
    private $formManager;
    private $entryController;

    public function formatArguments($arg)
    {
        $id = (!empty($_REQUEST['id'])) ? $_REQUEST['id'] : ($_REQUEST['id_typeannonce'] ?? ($arg['id'] ?? '')) ;

        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        return([
                'id' =>  $id,
                'mode' => isset($_POST['submit_file']) ? 'submitfile' :
                    ( isset($_POST['importfiche']) ? 'importentries' : 'default'),
                'importentries' => $_POST['importfiche'] ?? null,
                'filesData' => $_FILES['fileimport'] ?? null,
            ]);
    }
    
    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL())) {
            return $aclMessage;
        }

        // get services
        $this->importManager = $this->getService(ImportManager::class);
        $this->formManager = $this->getService(FormManager::class);
        $this->entryController = $this->getService(EntryController::class);

        // get Forms
        $forms = $this->formManager->getAll();

        // switch to right method
        switch ($this->arguments['mode']) {
            case 'submitfile':
                if ($extracted = $this->importManager->extractCSVfromCSVFile($this->arguments['id'], $this->arguments['filesData'])){                    
                    // append displayData
                    $extracted = array_map(function ($extract) {
                        $extract['displayData'] = $this->entryController->view($extract['entry'], '',0);
                        $extract['base64'] = base64_encode(serialize($extract['entry']));
                        return $extract;
                    },$extracted);
                }
                break;
            
            case 'importentries':
                $importedEntries = $this->importManager->importEntry($this->arguments['importentries'], $this->arguments['id']);
                break;
            
            case 'default':
            default:
                // get csv_template
                $csv_template = $this->importManager->getCSVfromFormId($this->arguments['id'], null, true) ;
                break;
        }

        return $this->render('@bazar/bazar-import.twig', [
            'id' => $this->arguments['id'],
            'forms' => $forms,
            'params' => [
                BAZ_VARIABLE_VOIR => BAZ_VOIR_IMPORTER],
            'csv' => isset($csv_template) ? $this->importManager->arrayToCSV($csv_template) : null,
            'selectedForm' => $this->formManager->getOne($this->arguments['id']),
            'importedEntries' => $importedEntries ?? null,
            'extracted' => $extracted ?? null,
            'mode' => $this->arguments['mode'],
        ]);
    }
}
