<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\ImportManager;

class BazarExportAction extends YesWikiAction
{
    private $importManager;
    private $formManager;

    public function formatArguments($arg)
    {
        $id = (!empty($_REQUEST['id'])) ? $_REQUEST['id'] : ($_REQUEST['id_typeannonce'] ?? ($arg['id'] ?? '')) ;

        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        return([
            'id' =>  $id,
            // chaine de recherche
            'q' =>  !empty($_GET['q']) ? $_GET['q'] : null,
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

        // get Forms
        $forms = $this->formManager->getAll();

        // get CSV
        $csv_raw = $this->importManager->getCSVfromFormId($this->arguments['id'], $this->arguments['q']) ;

        return $this->render('@bazar/bazar-export.twig', [
            'id' => $this->arguments['id'],
            'forms' => $forms,
            'params' => [
                BAZ_VARIABLE_VOIR => BAZ_VOIR_EXPORTER],
            'selectedForm' => $this->formManager->getOne($this->arguments['id']),
            'csv' => $this->importManager->arrayToCSV($csv_raw),
            'nbEntries' => !empty($csv_raw) ? count($csv_raw) - 1 : 0 ,
        ]);
    }
}
