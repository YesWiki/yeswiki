<?php

use YesWiki\Bazar\Service\EntryManager;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$entryManager = $this->services->get(EntryManager::class);

if ($entryManager->isEntry($this->GetPageTag())) {
    $this->AddJavascriptFile('tools/bazar/presentation/javascripts/bazar.js');

    $fiche = $entryManager->getOne($this->GetPageTag());

    $replace = '<input type="hidden" name="body" value="' . htmlspecialchars(json_encode($fiche), ENT_COMPAT, YW_CHARSET) . '" />';
    if (isset($_GET['time'])) {
        $replace = '<input type="hidden" name="time" value="' . htmlspecialchars($_GET['time'], ENT_COMPAT, YW_CHARSET) . '">' . "\n" . $replace;
    }
    $plugin_output_new = preg_replace(
        '/\<input type=\"hidden\" name=\"body\" value=\".*\" \/\>/Uis',
        $replace,
        $plugin_output_new
    );
}
