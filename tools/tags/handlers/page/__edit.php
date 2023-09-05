<?php

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Security\Controller\SecurityController;
use YesWiki\Tags\Service\TagsManager;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$params = $this->services->get(ParameterBagInterface::class);
if (!$params->get('hide_keywords') && $this->HasAccess("write") && $this->HasAccess("read")) {
    $response = array();
    // on recupere tous les tags du site
    $tagsManager = $this->services->get(TagsManager::class);
    $tab_tous_les_tags = $tagsManager->getAll();
    if (is_array($tab_tous_les_tags)) {
        foreach ($tab_tous_les_tags as $tab_les_tags) {
            $response[] = str_replace('\'', '&#39;', str_replace('"', '\"', $tab_les_tags['value']));
        }
    }
    sort($response);
    $tagsexistants = '\''.implode('\',\'', $response).'\'';


    $script = '$(function(){
    var tagsexistants = ['.$tagsexistants.'];
    var pagetag = $(\'#ACEditor .yeswiki-input-pagetag\');
	pagetag.tagsinput({
		typeahead: {
            afterSelect: function(val) {pagetag.tagsinput(\'input\').val(""); },
			source: tagsexistants,
            autoSelect:false,
        },
        trimValue: true,
		confirmKeys: [13, 186, 188],
	});
	
	//bidouille antispam
	$(".antispam").attr(\'value\', \'1\');

});'."\n";
    $this->AddJavascriptFile('tools/tags/libs/vendor/bootstrap-tagsinput.min.js');
    $this->AddJavascript($script);
}

//Sauvegarde
if (!$params->get('hide_keywords') && $this->HasAccess("write") &&
    isset($_POST["submit"]) && $_POST["submit"] == SecurityController::EDIT_PAGE_SUBMIT_VALUE &&
    isset($_POST["pagetags"]) && $_POST['antispam']==1) {
    $tagsManager->save($this->GetPageTag(), stripslashes($_POST["pagetags"]));
}
