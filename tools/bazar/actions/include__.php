<?php

use YesWiki\Bazar\Service\EntryManager;

$entryManager = $this->services->get(EntryManager::class);

// Si la page est une fiche bazar lisible, alors on affiche la fiche plutôt que de formater en wiki
if ($entryManager->isEntry($incPageName) && $this->HasAccess('read', $incPageName)) {
    $plugin_output_new = '<div class="' . $class . '">' . "\n" . baz_voir_fiche(0, $incPageName) . "\n" . '</div>' . "\n";
} else {
    $type = '';
}
