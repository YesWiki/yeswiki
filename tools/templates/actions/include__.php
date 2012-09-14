	<?php
if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

include_once 'tools/templates/libs/templates.functions.php';

// si la page inclue n'existe pas, on proposer de la créer
if (!$incPage = $this->LoadPage($incPageName)) {
	// on passe en parametres GET les valeurs du template de la page de provenance, pour avoir le même graphisme dans la page créée
	$query_string = 'theme='.urlencode($this->config['favorite_theme']).
					'&amp;squelette='.urlencode($this->config['favorite_squelette']).
					'&amp;style='.urlencode($this->config['favorite_style']);
	
	$plugin_output_new = '<div class="include '.$class.'">'."\n".
							'<a class="yeswiki-editable" href="'.$this->href('edit', $incPageName, $query_string).'">'.
							'<i class="icon-pencil"></i>&nbsp;'.TEMPLATE_EDIT.' '.$incPageName.'</a>'."\n".
						'</div>'."\n";
}
// sinon, on remplace les liens vers les NomWikis n'existant pas
else {
	$plugin_output_new = replace_missingpage_links($plugin_output_new);
}


//si le lien correspond à l'url, on rajoute une classe "actif"
if (!empty($actif) && $actif=="1") {
        $page_active=$this->tag;
        if (isset($oldpage) && $oldpage!='') { // si utilisation de l'extension attach
            $page_active = $oldpage;
        }
       $plugin_output_new = str_ireplacement('<a href="'.$this->config["base_url"].$page_active.'"',
       		'<a class="active-link" href="'.$this->config["base_url"].$page_active.'"',
       		$plugin_output_new);
}

//rajoute le javascript pour le double clic si le parametre est activé et les droits en écriture existent
if (!empty($dblclic) && $dblclic=="1" && $this->HasAccess("write", $incPageName)) {
	$actiondblclic = ' ondblclick="document.location=\''.$this->Href("edit", $incPageName).'\';"';
} else {
	$actiondblclic = '';
}
$plugin_output_new = str_replace('<div class="include', '<div'.$actiondblclic.' class="include div_include', $plugin_output_new);

//on enleve le préfixe include_ des classes pour que le parametre passé et le nom de classe CSS soient bien identiques 
$plugin_output_new = str_replace('include_', '', $plugin_output_new);

//on rajoute une div clear pour mettre le flow css en dessous des éléments flottants
$plugin_output_new =  (!empty($clear) && $clear=="1") ? $plugin_output_new.'<div class="clear"></div>'."\n" : $plugin_output_new;


?>
	