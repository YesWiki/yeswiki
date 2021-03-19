<?php

use YesWiki\Core\YesWikiAction;

class BazarListePreRenderAction extends YesWikiAction
{
    private $error ;

    function formatArguments($arg)
    {
        $this->error = '' ;
        $this->error .= ($this->checkIndex($arg,'calledBy')) ? 
            (
                ($arg['calledBy'] != 'BazarListeAction') ? 
                  '<br/>' . get_class($this) . ' can only be called by "BazarListeAction" !' :
                  ''
            ):
            '';
        $this->checkIndex($arg,'listId') ;
        $this->checkIndex($arg,'numEntries') ;
        $this->checkIndex($arg,'forms') ;
        $this->checkIndex($arg,'pageTag') ;
        return([
            
        ]);
    }

    private function checkIndex(array $arg,string $index): bool
    {
        if (isset($arg[$index])) {
            return true ;
        } else {
            $this->error .= '<br/>The item \''.$index.'\' shoud be defined in arguments';
            return false
        }
    }

    function run()
    {
        if (!empty($this->error)) {
            $this->error = 'Error occured in ' . self::class . ' :' . $this->error ;
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => $this->error
            ]);
        }

        return $this->render('@bazar/entries/list.twig', $this->arguments]);
    }

    // this method is called by child class before render template
    protected function preRender(? array $arg): ? array
    {
        return $arg;
    }
}
