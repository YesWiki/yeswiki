<?php

use YesWiki\Templates\Service\Utils;

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}


/**
 *
 * Verifie si le nombre d'elements graphiques d'un type trouvés et de leur fermeture correspondent
 *
 * @param $element : name of element
 *
 * return bool vrai si chaque élément est bien fermé
 * @deprecated use \YesWiki\Templates\Service\Utils::checkGraphicalElements
 */
function check_graphical_elements($element, $pagetag, $pagecontent)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->checkGraphicalElements($element, $pagetag, $pagecontent);
}


/**
 *
 * Parcours des dossiers a la recherche de templates
 *
 * @param $directory : chemin relatif vers le dossier contenant les templates
 *
 * return array : tableau des themes trouves, ranges par ordre alphabetique
 * @deprecated use \YesWiki\Templates\Service\Utils::searchTemplateFiles
 */
function search_template_files($directory)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->searchTemplateFiles($directory);
}

/**
 * @deprecated use \YesWiki\Templates\Service\Utils::removeExtension
 */
function remove_extension($filename)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->removeExtension($filename);
}

// str_ireplace est php5 seulement
if (!function_exists('str_ireplacement')) {
    /**
     * @deprecated use \YesWiki\Templates\Service\Utils::strIreplacement
     */
    function str_ireplacement($search, $replace, $subject)
    {
        return $GLOBALS['wiki']->services->get(Utils::class)->strIreplacement($search, $replace, $subject);
    }
}

/**
 *
 * cree un diaporama a partir d'une PageWiki
 *
 * @param $pagetag : nom de la PageWiki
 * @param $template : fichier template pour le diaporama
 * @param $class : classe CSS a ajouter au diaporama
 * 
 * @deprecated use \YesWiki\Templates\Service\Utils::printDiaporama
 *
 */
function print_diaporama($pagetag, $template = 'diaporama_slides.tpl.html', $class = '')
{
    return $GLOBALS['wiki']->services->get(Utils::class)->printDiaporama($pagetag, $template, $class);
}

/**
 * @deprecated use \YesWiki\Templates\Service\Utils::showFormThemeSelector
 */
function show_form_theme_selector($mode = 'selector', $formclass = '')
{
    return $GLOBALS['wiki']->services->get(Utils::class)->showFormThemeSelector($mode, $formclass);
}


/**
 * recupere le parametre data sous forme d'un tableau
 *
 *
 * @return array or null if not result
 * @deprecated use \YesWiki\Templates\Service\Utils::getDataParameter
 */
function getDataParameter()
{
    return $GLOBALS['wiki']->services->get(Utils::class)->getDataParameter();
}

/**
 * @deprecated use \YesWiki\Templates\Service\Utils::postFormat
 */
function postFormat($output)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->postFormat($output);
}

/**
 * Récupère les droits de la page désignée en argument et renvoie un tableau.
 *
 * @param string $page
 * @return array
 * @deprecated use \YesWiki\Templates\Service\Utils::recupDroits
 */
function recup_droits($page)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->recupDroits($page);
}

/**
 * Get the first image in the page
 *
 * @param array  $page   Page info
 * @param string $width  Width of the image
 * @param string $height Height of the image
 *
 * @return string  link to the image
 * @deprecated use \YesWiki\Templates\Service\Utils::getImageFromBody
 */
function getImageFromBody($page, $width, $height)
{
    if (!is_array($page) || empty($page['tag']) || !is_scalar($width) || !is_scalar($height)){
        return '';
    }
    return $GLOBALS['wiki']->services->get(Utils::class)->getImageFromBody($page, strval($width), strval($height));
}
/**
 * Get the first title in page
 *
 * @param array $page Informations de la page
 *
 * @return string The title string
 * @deprecated use \YesWiki\Templates\Service\Utils::getTitleFromBody
 */
function getTitleFromBody($page)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->getTitleFromBody($page);
}

/**
 * Get the first title in page
 *
 * @param array $page   Page informations
 * @param string $title The page title
 * @param int   $length Max number of chars (default 300)
 *
 * @return string The title string
 * @deprecated use \YesWiki\Templates\Service\Utils::getDescriptionFromBody
 */
function getDescriptionFromBody($page, $title, $length = 300)
{
    return $GLOBALS['wiki']->services->get(Utils::class)->getDescriptionFromBody($page, $title, $length);
}
