<?php

namespace YesWiki\Bazar\Controller;

use YesWiki\Core\YesWikiController;

class templateNamePreRenderer extends YesWikiController
{

    public function preRender(?array $data): ?array
    {
        foreach ($data['fiches'] as $id => $fiche){
            $fiche['bf_titre'] = 'Titre de test';
            $data['fiches'][$id] = $fiche;
        }
        return $data ;
    }

}