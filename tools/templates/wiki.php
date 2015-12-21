<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// 
/**
 * Fichier de lancement et de configuration de l'extension Templates
 *
 *@package         templates
 *@author        Florian Schmitt <florian@outils-reseaux.org>
 *@copyright     2012 Outils-Réseaux
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

require_once 'tools/templates/libs/templates.functions.php';

// Theme par défaut
define('THEME_PAR_DEFAUT', 'yeswiki');

// Style par défaut
define('CSS_PAR_DEFAUT', 'gray.css');

// Squelette par défaut
define('SQUELETTE_PAR_DEFAUT', 'responsive-1col.tpl.html');

// Image de fond par défaut
define('BACKGROUND_IMAGE_PAR_DEFAUT', '');

// Pour que seul le propriétaire et l'admin puissent changer de theme
define('SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME', false);

// Indique un encodage de caractères par defaut
define('TEMPLATES_DEFAULT_CHARSET', 'UTF-8');

// Surcharge  fonction  LoadRecentlyChanged : suppression remplissage cache car affecte le rendu du template.
$wikiClasses[] = 'Template';


$wikiClassesContent [] = '
    function AddCSS($style) {    
        if (!isset($GLOBALS[\'css\'])) {
            $GLOBALS[\'css\'] = \'\';
        }
        if (!empty($style) && !strpos($GLOBALS[\'css\'], \'<style>\'."\n".$style.\'</style>\')) {
            $GLOBALS[\'css\'] .= \'  <style>\'."\n".$style.\'</style>\'."\n";
        }
        return;
    }

    function AddCSSFile($file, $conditionstart=\'\', $conditionend=\'\') {    
        if (!isset($GLOBALS[\'css\'])) {
            $GLOBALS[\'css\'] = \'\';
        }
        if (!strpos($GLOBALS[\'css\'], \'<link rel="stylesheet" href="\'.$file.\'">\') && (!empty($file) && (file_exists($file) || strpos($file, "http://") === 0))) {
            $GLOBALS[\'css\'] .= \'  \'.$conditionstart."\n"
                .\'    <link rel="stylesheet" href="\'.$file.\'">\'."\n"
                .\'  \'.$conditionend."\n";
        }
        return;
    }

    function AddJavascript($script) {    
        if (!isset($GLOBALS[\'js\'])) {
            $GLOBALS[\'js\'] = \'\';
        }
        if (!empty($script) && !strpos($GLOBALS[\'js\'], \'<script>\'."\n".$script.\'</script>\')) {
            $GLOBALS[\'js\'] .= \'  <script>\'."\n".$script.\'</script>\'."\n";
        }
        return;
    }

    function AddJavascriptFile($file) {    
        if (!isset($GLOBALS[\'js\'])) {
            $GLOBALS[\'js\'] = \'\';
        }
        if (!strpos($GLOBALS[\'js\'], \'<script src="\'.$file.\'"></script>\') && !empty($file) && (file_exists($file) || strpos($file, "http://") === 0)) {
            $GLOBALS[\'js\'] .= \'  <script src="\'.$file.\'"></script>\'."\n";
        }
        return;
    }

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
    
    
    function GetMetaDatas($pagetag) {    
        $metadatas = $this->GetTripleValue($pagetag, \'http://outils-reseaux.org/_vocabulary/metadata\', \'\', \'\', \'\');
        if (!empty($metadatas)) {
            if (TEMPLATES_DEFAULT_CHARSET != \'UTF-8\') return array_map(\'utf8_decode\', json_decode($metadatas, true));
            else return json_decode($metadatas, true);
        }
        else {
            return false;
        }
    }
    
    
    function SaveMetaDatas($pagetag, $metadatas) {
        $former_metadatas = $this->GetMetaDatas($pagetag);

        if ($former_metadatas)
        {
            $metadatas = array_merge($former_metadatas, $metadatas);
            $this->DeleteTriple($pagetag, \'http://outils-reseaux.org/_vocabulary/metadata\', null, \'\', \'\');
        }
        if (TEMPLATES_DEFAULT_CHARSET != \'UTF-8\') $metadatas = json_encode(array_map("utf8_encode", $metadatas));
        else $metadatas = json_encode($metadatas);
        return $this->InsertTriple($pagetag, \'http://outils-reseaux.org/_vocabulary/metadata\', $metadatas, \'\', \'\');
    }
    
';

//on récupère les metadonnées de la page
$metadatas = $wiki->GetTripleValue(
    $page,
    'http://outils-reseaux.org/_vocabulary/metadata',
    '',
    '',
    ''
);

if (!empty($metadatas)) {
    if (TEMPLATES_DEFAULT_CHARSET != 'UTF-8') $metadatas = array_map('utf8_decode', json_decode($metadatas, true));
    else $metadatas = json_decode($metadatas, true);
}

if (isset($metadatas['charset'])) {
    $wakkaConfig['charset'] = $metadatas['charset'];
} elseif (!isset($wakkaConfig['charset'])) {
    $wakkaConfig['charset'] = TEMPLATES_DEFAULT_CHARSET;
}

header('Content-Type: text/html; charset=' . TEMPLATES_DEFAULT_CHARSET);

// Premier cas le template par défaut est forcé : on ajoute ce qui est présent dans le fichier de configuration, ou le theme par defaut précisé ci dessus
if (isset($wakkaConfig['hide_action_template']) && $wakkaConfig['hide_action_template']=='1' ) {
    if (!isset($wakkaConfig['favorite_theme'])) $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
    if (!isset($wakkaConfig['favorite_style'])) $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
    if (!isset($wakkaConfig['favorite_squelette'])) $wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT; 
    if (!isset($wakkaConfig['favorite_background_image'])) $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT; 
}
// Sinon, on récupère premièrement les valeurs passées en REQUEST, ou deuxièmement les métasdonnées présentes pour la page, ou troisièmement les valeurs du fichier de configuration
else {
    if (isset($_REQUEST['theme']) && (is_dir('themes/'.$_REQUEST['theme']) || is_dir('tools/templates/themes/'.$_REQUEST['theme'])) &&
        isset($_REQUEST['style']) && (is_file('themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style']) || is_file('tools/templates/themes/'.$_REQUEST['theme'].'/styles/'.$_REQUEST['style'])) &&
        isset($_REQUEST['squelette']) && (is_file('themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']) || is_file('tools/templates/themes/'.$_REQUEST['theme'].'/squelettes/'.$_REQUEST['squelette']))
        ) {
        $wakkaConfig['favorite_theme'] = $_REQUEST['theme'];
        $wakkaConfig['favorite_style'] = $_REQUEST['style'];
        $wakkaConfig['favorite_squelette'] = $_REQUEST['squelette'];

        if (isset($_REQUEST['bgimg']) && (is_file('files/backgrounds/'.$_REQUEST['bgimg']) )) {
            $wakkaConfig['favorite_background_image'] = $_REQUEST['bgimg'];
        } else {
            $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
        }

    } else {
        // si les metas sont présentes on les utilise
        if (isset($metadatas['theme']) && isset($metadatas['style']) && isset($metadatas['squelette'])) {
            $wakkaConfig['favorite_theme'] = $metadatas['theme'];
            $wakkaConfig['favorite_style'] = $metadatas['style'];
            $wakkaConfig['favorite_squelette'] = $metadatas['squelette'];
            if (isset($metadatas['bgimg'])) {
                $wakkaConfig['favorite_background_image'] = $metadatas['bgimg'];
            } else {
                $wakkaConfig['favorite_background_image'] = '';
            }
            
        }
        //on récupére les valeurs du template associées à la page de l'ancienne version de templates
        else {
            //on récupère le contenu de la page
            $contenu = $wiki->LoadPage($page);
            if ($act = preg_match_all("/".'(\\{\\{template)'.'(.*?)'.'(\\}\\})'."/is", $contenu["body"], $matches)) {
                $i = 0;
                $j = 0;
                foreach ($matches as $valeur) {
                    foreach ($valeur as $val) {
                        if (isset($matches[2][$j]) && $matches[2][$j]!='') {
                            $action= $matches[2][$j];
                            if (preg_match_all("/([a-zA-Z0-9]*)=\"(.*)\"/U", $action, $params)) {
                                for ($a = 0; $a < count($params[1]); $a++) {
                                    $vars[$params[1][$a]] = $params[2][$a];
                                }
                            }
                        }
                        $j++;
                    }
                    $i++;
                }
            }
        }
        // des valeurs ont été trouvées, on les utilise
        if ((isset($vars["theme"]) && $vars["theme"]!="") && (isset($vars["style"]) && $vars["style"]!="") && (isset($vars["squelette"]) && $vars["squelette"]!="")) {
            $wakkaConfig['favorite_theme'] = $vars["theme"];
            $wakkaConfig['favorite_style'] = $vars["style"];
            $wakkaConfig['favorite_squelette'] = $vars["squelette"];
            $wakkaConfig['favorite_background_image'] = '';
        } else {
            if (!isset($wakkaConfig['favorite_theme'])) $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
            if (!isset($wakkaConfig['favorite_style'])) $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
            if (!isset($wakkaConfig['favorite_squelette'])) $wakkaConfig['favorite_squelette'] = SQUELETTE_PAR_DEFAUT;
            if (!isset($wakkaConfig['favorite_background_image'])) $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
        }
    }
}

/**
 * vérifie l'extension d'un fichier
 *
 * Compare l'extension du fichier dont le nom est passé en paramètre à une
 * extension. Retourne vrai si l'extension correspond sinon retourne faux.
 * @param  string $filename Nom du fichier dont l'extension est a vérifer
 * @param  string $ext      extension attendue
 * @return bool
 */
function isExtension($filename, $ext)
{
    return (substr($filename, -strlen($ext), strlen($filename)) === $ext);
}

// Test existence du template, on utilise le template par defaut sinon=============================================
if ((!file_exists('tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette'])
    && !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/squelettes/'.$wakkaConfig['favorite_squelette'])
    && !preg_match('/\.tpl\.html$/', $wakkaConfig['favorite_squelette']))
    || (!file_exists('tools/templates/themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style'])
    && !file_exists('themes/'.$wakkaConfig['favorite_theme'].'/styles/'.$wakkaConfig['favorite_style'])
    && !preg_match('/\.css$/', $wakkaConfig['favorite_style']))) {
        if ((file_exists('tools/templates/themes/'.THEME_PAR_DEFAUT.'/squelettes/'.SQUELETTE_PAR_DEFAUT) ||
             file_exists('themes/'.THEME_PAR_DEFAUT.'/squelettes/'.SQUELETTE_PAR_DEFAUT)
            ) &&
            (file_exists('tools/templates/themes/'.THEME_PAR_DEFAUT.'/styles/'.CSS_PAR_DEFAUT) ||
             file_exists('themes/'.THEME_PAR_DEFAUT.'/styles/'.CSS_PAR_DEFAUT)
            )
           ) {
            $GLOBALS['template-error']['type'] = 'theme-not-found';
            $GLOBALS['template-error']['theme'] = $wakkaConfig['favorite_theme'];
            $GLOBALS['template-error']['style'] = $wakkaConfig['favorite_style'];
            $GLOBALS['template-error']['squelette'] = $wakkaConfig['favorite_squelette'];
            $wakkaConfig['favorite_theme'] = THEME_PAR_DEFAUT;
            $wakkaConfig['favorite_style'] = CSS_PAR_DEFAUT;
            $wakkaConfig['favorite_squelette']= SQUELETTE_PAR_DEFAUT;
            $wakkaConfig['favorite_background_image'] = BACKGROUND_IMAGE_PAR_DEFAUT;
        } else {
            exit('<div class="alert alert-danger">'._t('TEMPLATE_NO_DEFAULT_THEME').'.</div>');
        }
}
