<?php
if (!defined("WIKINI_VERSION")) {
	die ("acc&egrave;s direct interdit");
}

include_once 'tools/templates/libs/templates.functions.php';

// Dans Wakka.config.php, on peut preciser : favorite_theme, favorite_style, favorite_squelette,  hide_action_template 
// Sinon, on prend les parametres ci dessous :

// Configuration du fonctionnement des templates : faut il laisser le choix autre que par défaut 
define('FORCER_TEMPLATE_PAR_DEFAUT', (isset($wakkaConfig['hide_action_template'])) ? $wakkaConfig['hide_action_template'] : false);

//Theme par défaut
define ('THEME_PAR_DEFAUT', (isset($wakkaConfig['favorite_theme'])) ? $wakkaConfig['favorite_theme'] : 'yeswiki');

//Style par défaut
define ('CSS_PAR_DEFAUT', (isset($wakkaConfig['favorite_style'])) ? $wakkaConfig['favorite_style'] : 'yeswiki.css');

//squelette par défaut
define ('SQUELETTE_PAR_DEFAUT', (isset($wakkaConfig['favorite_squelette'])) ? $wakkaConfig['favorite_squelette'] : 'yeswiki.tpl.html');

//pour que seul le propriétaire et l'admin puissent changer de theme
define ('SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME', true);

// Desactivation de l'extension template si l'extension navigation est presente et active. 
if (isset($plugins_list['navigation'])) {
	unset($k);	
	return;
}

//on cherche tous les dossiers du repertoire themes et des sous dossier styles et squelettes, et on les range dans le tableau $wakkaConfig['templates']
$repertoire_initial = 'tools'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'themes';
$wakkaConfig['templates'] = search_template_files($repertoire_initial);

//s'il y a un repertoire themes a la racine, on va aussi chercher les templates dedans
if (is_dir('themes')) {
	$repertoire_racine = 'themes';
	$wakkaConfig['templates'] = array_merge($wakkaConfig['templates'], search_template_files($repertoire_racine));
	if (is_array($wakkaConfig['templates'])) ksort($wakkaConfig['templates']);
}


//si le theme est passé en paramètre, on l'utilise
//=======Changer de theme=================================================================================================
if (isset($_REQUEST['theme'])  && array_key_exists($_REQUEST['theme'], $wakkaConfig['templates'])) {
	$wakkaConfig['favorite_theme'] = $_REQUEST['theme'];
}
else {
	$wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
}

//=======Changer de style=====================================================================================================
$styles['none']='pas de style';
if (isset($_REQUEST['style']) && array_key_exists($_REQUEST['style'], $wakkaConfig['templates'][$wakkaConfig['favorite_theme']]['style'])) {
	$wakkaConfig['favorite_style'] = $_REQUEST['style'];
}
else {
	$wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
}

//=======Changer de squelette=================================================================================================    
if(isset($_REQUEST['squelette']) && array_key_exists($_REQUEST['squelette'], $wakkaConfig['templates'][$wakkaConfig['favorite_theme']]['squelette'])) {
	$wakkaConfig['favorite_squelette'] = $_REQUEST['squelette'];
}
else {
	$wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
}


if (!isset($wakkaConfig['hide_action_template'])) {
	$wakkaConfig['hide_action_template'] = FORCER_TEMPLATE_PAR_DEFAUT;
} 

if (!isset($wakkaConfig['favorite_theme'])) {
	$wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
}

if (!isset($wakkaConfig['favorite_style'])) {
	$wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
}

if (!isset($wakkaConfig['favorite_squelette'])) {
	$wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
} 


// Surcharge  fonction  LoadRecentlyChanged : suppression remplissage cache car affecte le rendu du template.
$wikiClasses [] = 'Template';


$wikiClassesContent [] = ' 
	function LoadRecentlyChanged($limit=50)
        {
                $limit= (int) $limit;
                if ($pages = $this->LoadAll("select id, tag, time, user, owner from ".$this->config["table_prefix"]."pages where latest = \'Y\' and comment_on =  \'\' order by time desc limit $limit"))
                {
                        return $pages;
                }
        }	
    function GetMethod() {
	  	if ($this->method==\'iframe\')
	  	{
			return \'show\';
	    } 
	    else
	    {
			return Wiki::GetMethod();
		}
    }	
';	


//on cherche l'action template dans la page, qui definit le graphisme a utiliser, dans le cas d'un aperçu
if (isset($_POST["submit"]) && $_POST["submit"] == html_entity_decode('Aper&ccedil;u')) {
	$contenu["body"] = $_POST["body"].'{{template theme="'.$_POST["theme"].'" squelette="'.$_POST["squelette"].'" style="'.$_POST["style"].'"}}';	
	$_POST["body"] = $_POST["body"].'{{template theme="'.$_POST["theme"].'" squelette="'.$_POST["squelette"].'" style="'.$_POST["style"].'"}}';
} 

else {
	$contenu = $wiki->LoadPage($page);
}


//on récupére les valeurs du template associées à la page
if (!$wakkaConfig['hide_action_template'] && $act=preg_match_all ("/".'(\\{\\{template)'.'(.*?)'.'(\\}\\})'."/is", $contenu["body"], $matches)) {
     $i = 0; $j = 0;
     foreach($matches as $valeur) {
       foreach($valeur as $val) {
       	
         if (isset($matches[2][$j]) && $matches[2][$j]!='') {
           $action= $matches[2][$j];
           if (preg_match_all("/([a-zA-Z0-9]*)=\"(.*)\"/U", $action, $params))
			{
				for ($a = 0; $a < count($params[1]); $a++)
				{
					$vars[$params[1][$a]] = $params[2][$a];
				}
			}
         }
         $j++;
       }
       $i++;
     }
   }
if (isset($vars["theme"]) && $vars["theme"]!="") {
	 $wakkaConfig['favorite_theme'] = $vars["theme"]; 
}
if (isset($vars["style"]) && $vars["style"]!="") {
 	$wakkaConfig['favorite_style'] = $vars["style"];
}
if  (isset($vars["squelette"]) && $vars["squelette"]!="") {
	$wakkaConfig['favorite_squelette'] = $vars["squelette"];
}

//=======Test existence du template, on utilise le template par defaut sinon=======================================================
if (
	(!file_exists('tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette']) &&
	 !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette'])
	) || 
	(!file_exists('tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style']) && 
	 !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style'])
	) 
) {
	if (file_exists('tools/templates/themes/yeswiki/squelettes/yeswiki.tpl.html')
		&& file_exists('tools/templates/themes/yeswiki/styles/yeswiki.css')) {
		$wakkaConfig['favorite_theme']='yeswiki';
		$wakkaConfig['favorite_style']='yeswiki.css';
		$wakkaConfig['favorite_squelette']='yeswiki.tpl.html';
		echo 'Certains (ou tous les) fichiers du template '.$wakkaConfig['favorite_theme'].' ont disparu (tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette'].' et/ou tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style'].').<br />Le template par d&eacute;faut est donc utilis&eacute;.';
} else {
		exit('Les fichiers du template par d&eacute;faut ont disparu, l\'utilisation des templates est impossible.<br />Veuillez r&eacute;installer le tools template ou contacter l\'administrateur du site.');
	}
}

?>
