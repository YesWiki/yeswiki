<?php

use YesWiki\Core\YesWikiAction;
use YesWiki\Bazar\Service\ImportManager;

class BazarImportAction extends YesWikiAction
{
    private $importManager;

    public function formatArguments($arg)
    {
        $id = (!empty($_REQUEST['id'])) ? $_REQUEST['id'] : ($_REQUEST['id_typeannonce'] ?? ($arg['id'] ?? '')) ;

        //on transforme en entier, pour eviter des attaques
        $id = (int)preg_replace('/[^\d]+/', '', $id);

        return([
            'id' =>  $id
            ]);
    }
    
    public function run()
    {
        if (!empty($aclMessage = $this->checkSecuredACL())) {
            return $aclMessage;
        }

        // get services
        $this->importManager = $this->getService(ImportManager::class);

        $output = '';
    
        return $output;
    }
}
