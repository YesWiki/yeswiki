<?php

use YesWiki\Bazar\Service\EntryManager;



$entryManager = $this->services->get(EntryManager::class);

// Si la page est une fiche bazar, alors on affiche la fiche plutôt que de formater en wiki
if ($entryManager->isEntry($incPageName)) {
    $plugin_output_new = '<div class="'.$class.'">'."\n".baz_voir_fiche(0, $incPageName)."\n".'</div>'."\n";
} else {
    $type = '';
}
