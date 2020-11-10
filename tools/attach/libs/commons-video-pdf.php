<?php
/**
 * Commons script for actions video and pdf
 *
 *
 * @category YesWiki
 * @package  attach
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 *
 *
 * @param    $class            string   Classe fournit pour déterminer la position et à ajouter au conteneur
 * @param    $manageSize       boolean Référence vers le boolean Y a-t-il une gestion de la taille ?
 * @param    $managePosition   boolean Référence vers le boolean représantant le paramètre de gestion de la position
 * @param    $maxWidth         number   Largeur maximale de la zone
 * @param    $maxHeight        number   Hauteur maximale de la zone
 * @param    $styleForSize     string   Texte pour définir le style et déterminer la taille de la zone
 * @param    $class_for_embed  string  Référence vers la chaîne de texte représentant la classe pour le container embed
 */
$styleForSize = ($manageSize) ? ' style="max-width:'.$maxWidth.'px;max-height:'.$maxHeight .'px;"' : '' ;
    
//position
$class = $baseObject->GetParameter("class");
$managePosition = false ;
$class_for_div = '' ;
$class_for_embed = '' ;

if (!empty($class)) {
    $class = str_replace('attached_file', '', $class) ; // to avoid display troubles
    if (!(strpos($class, 'pull-left') === false) || !(strpos($class, 'pull-right') === false)) {
        if ($manageSize) {
            $manageSize = false ;
            $managePosition = true ;
            $divHTML = '<div style="width:' . $maxWidth . 'px;height:' . $maxHeight . 'px;max-width:100%;' ;
            $divHTML .= '" class="' . $class . '">' ;
            echo $divHTML ;
        } else {
            // remove class because not usefull
            //$class_for_embed  = ' ' . str_replace('pull-right','',str_replace('pull-left','',$class)) ;
            
            $managePosition = true ;
            $divHTML = '<div style="width:100%;' ;
            $divHTML .= '" class="' . $class . '">' ;
            echo $divHTML ;
        }
    } else {
        if ($manageSize) {
            $class_for_div = 'class="' . $class . '"';
        } else {
            $class_for_embed = ' ' . $class ;
        }
    }
}

if ($manageSize) {
    echo '<div'. $styleForSize . $class_for_div . '>' ;
}
