<?php
/**
 * Action to display a pdf in an embedded reader
 *
 * @param url  required The url of the pdf file. The url has to be from the same origin than the wiki (same schema, same host & same port)
 * @param forme shape for the container : possible values empty (default), 'portrait' - 'paysage' - 'carre'
 * @param largeurmax  the maximum wanted width ; number without "px"
 * @param hauteurmax  the maximum wanted heigth ; number without "px"
 * @param position position for the container ; 'right' - 'left'
 *
 * @category YesWiki
 * @package  attach
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// add attach extension css style for pdf
//$this->AddCSSFile(ATTACH_PATH . 'presentation/styles/attach.css');
// already called by linkstyle__.php

$baseObject = (get_class($this) == "attach") ? $this->wiki : $this ;
$url = $baseObject->GetParameter("url");

if (empty($url) || parse_url($url, PHP_URL_HOST) != $_SERVER['SERVER_NAME'] ||
    (parse_url($url, PHP_URL_PORT) ==  '' && $_SERVER['SERVER_PORT'] != '' &&  $_SERVER['SERVER_PORT'] != '80'
        && $_SERVER['SERVER_PORT'] != '443')  ||
    (parse_url($url, PHP_URL_PORT) != '' && parse_url($url, PHP_URL_PORT) != $_SERVER['SERVER_PORT']) ||
    parse_url($url, PHP_URL_SCHEME) != $_SERVER['REQUEST_SCHEME']){
    echo '<div class="alert alert-danger">' . _t('ATTACH_ACTION_PDF_PARAM_URL_ERROR') . '</div>' . "\n";
} else {
	$forme = $baseObject->GetParameter("forme");
	switch ($forme){
		case "portrait":
			$forme = "pdf" ;
			$ratio = 1.38 ;
			break;
		case "paysage":
			$forme = "pdf-landscape" ;
			$ratio = 0.75 ;
			break;
		case "carre":
			$forme = "pdf-square" ;
			$ratio = 1 ;
			break;
		default:
			$forme = "pdf" ;
			$ratio = 1.38 ;
	}
	//size 
	$maxWidth = $baseObject->GetParameter("largeurmax");
	$maxHeight = $baseObject->GetParameter("hauteurmax");
	$manageSize = false ;
	if (!empty($maxWidth) && $maxWidth != '' && is_numeric($maxWidth)) {
		$manageSize = true ;
		if (empty($maxHeight) || $maxHeight == '' || !(is_numeric($maxHeight))) {
			$maxHeight = $maxWidth * $ratio ;
		} else {
			// calculte the minimum between width and height
			$newMaxHeight = min($maxWidth * $ratio,$maxHeight) ;
			$newMaxWidth = min($maxHeight / $ratio,$maxWidth) ;
			$maxHeight = $newMaxHeight ;
			$maxWidth = $newMaxWidth ;
		}
	} elseif (!empty($maxHeight) && $maxHeight != '' && is_numeric($maxHeight)) {
		$manageSize = true ;
		if (empty($maxWidth) || $maxWidth != '' || !(is_numeric($maxWidth))) {
			$maxWidth = $maxHeight / $ratio ;
		}
	}
	$styleForSize = ($manageSize) ? ' style="max-width:'.$maxWidth.'px;max-height:'.$maxHeight .'px;"' : '' ;
	
	//position
	$position = $baseObject->GetParameter("position");
	$managePosition = false ;
	if (!empty($position) && in_array(strtolower($position), array('left','right')) && $manageSize) {
		$manageSize = false ;
		$managePosition = true ;
		$divHTML = '<div style="width:' . $maxWidth . 'px;height:' . $maxHeight . 'px;max-width:100%;' ;
		$divHTML .= 'float:' . strtolower($position) . ';' ;
		$divHTML .= '">' ;
		echo $divHTML ;
	}
	if($manageSize) { echo '<div'. $styleForSize .'>' ;}
    echo '<div class="embed-responsive ' . $forme . '"'. $styleForSize . '><iframe src="tools/attach/libs/vendor/pdfjs-dist/web/viewer.html?file='
        . urlencode($url) . '" class="embed-responsive-item" frameborder="0" allowfullscreen></iframe></div>';
	if($manageSize) { echo '</div>' ;}
	if($managePosition) { echo '</div>' ;}
}
