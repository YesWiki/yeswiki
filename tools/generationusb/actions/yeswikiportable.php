<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 1999-2006 outils-reseaux.org                                                            |
// +------------------------------------------------------------------------------------------------------+
// | This file is part of wkgenerationusb.                                                                     |
// |                                                                                                      |
// | Foobar is free software; you can redistribute it and/or modify                                       |
// | it under the terms of the GNU General Public License as published by                                 |
// | the Free Software Foundation; either version 2 of the License, or                                    |
// | (at your option) any later version.                                                                  |
// |                                                                                                      |
// | Foobar is distributed in the hope that it will be useful,                                            |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                                        |
// | GNU General Public License for more details.                                                         |
// |                                                                                                      |
// | You should have received a copy of the GNU General Public License                                    |
// | along with Foobar; if not, write to the Free Software                                                |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// CVS : $Id: bazar.php,v 1.13 2010-12-15 11:15:45 ddelon Exp $
/**
* portableyeswiki.php
*
* Description : action pour afficher un lien de téléchargement du yeswikiportable, s'il existe
*
*@package wkgenerationusb
*@author        Florian SCHMITT <florian@outils-reseaux.org>
*@copyright     Florian SCHMITT 2011
*
*/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
}

if (is_file("files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip")) {
	echo	'<style>
	@font-face{
    font-family: \'WebSymbolsRegular\';
    src: url(\'tools/generationusb/presentation/fonts/websymbols-regular-webfont.eot\');
    src: url(\'tools/generationusb/presentation/fonts/websymbols-regular-webfont.eot?#iefix\') format(\'embedded-opentype\'),
        url(\'tools/generationusb/presentation/fonts/websymbols-regular-webfont.woff\') format(\'woff\'),
        url(\'tools/generationusb/presentation/fonts/websymbols-regular-webfont.ttf\') format(\'truetype\'),
        url(\'tools/generationusb/presentation/fonts/websymbols-regular-webfont.svg#WebSymbolsRegular\') format(\'svg\');
    font-weight: normal;
    font-style: normal;
}
.a-btn{
    -webkit-border-radius:50px;
    -moz-border-radius:50px;
    border-radius:50px;
    padding:10px 30px 10px 70px;
    position:relative;
    float:left;
    display:block;
    overflow:hidden;
    margin:10px;
    background:#fff;
    background:-webkit-gradient(linear,left top,left bottom,color-stop(rgba(255,255,255,1),0),color-stop(rgba(246,246,246,1),0.74),color-stop(rgba(237,237,237,1),1));
    background:-webkit-linear-gradient(top, rgba(255,255,255,1) 0%, rgba(246,246,246,1) 74%, rgba(237,237,237,1) 100%);
    background:-moz-linear-gradient(top, rgba(255,255,255,1) 0%, rgba(246,246,246,1) 74%, rgba(237,237,237,1) 100%);
    background:-o-linear-gradient(top, rgba(255,255,255,1) 0%, rgba(246,246,246,1) 74%, rgba(237,237,237,1) 100%);
    background:linear-gradient(top, rgba(255,255,255,1) 0%, rgba(246,246,246,1) 74%, rgba(237,237,237,1) 100%);
    filter:progid:DXImageTransform.Microsoft.gradient( startColorstr=\'#ffffff\', endColorstr=\'#ededed\',GradientType=0 );
    -webkit-box-shadow:0px 0px 7px rgba(0,0,0,0.2), 0px 0px 0px 1px rgba(188,188,188,0.1);
    -moz-box-shadow:0px 0px 7px rgba(0,0,0,0.2), 0px 0px 0px 1px rgba(188,188,188,0.1);
    box-shadow:0px 0px 7px rgba(0,0,0,0.2), 0px 0px 0px 1px rgba(188,188,188,0.1);
    -webkit-transition:box-shadow 0.3s ease-in-out;
    -moz-transition:box-shadow 0.3s ease-in-out;
    -o-transition:box-shadow 0.3s ease-in-out;
    transition:box-shadow 0.3s ease-in-out;
}
.a-btn-symbol{
    font-family:\'WebSymbolsRegular\', cursive;
    color:#555;
    font-size:20px;
    text-shadow:1px 1px 2px rgba(255,255,255,0.5);
    position:absolute;
    left:20px;
    line-height:32px;
    -webkit-transition:opacity 0.3s ease-in-out;
    -moz-transition:opacity 0.3s ease-in-out;
    -o-transition:opacity 0.3s ease-in-out;
    transition:opacity 0.3s ease-in-out;
}
.a-btn-text{
    font-size:20px;
    color:#d7565b;
    line-height:16px;
    font-weight:bold;
    font-family:"Myriad Pro", "Trebuchet MS", sans-serif;
    text-shadow:1px 1px 2px rgba(255,255,255,0.5);
    display:block;
}
.a-btn-slide-text{
    font-family:Arial, sans-serif;
    font-size:10px;
    letter-spacing:1px;
    text-transform:uppercase;
    color:#555;
    text-shadow:0px 1px 1px rgba(255,255,255,0.9);
}
.a-btn-slide-icon{
    position:absolute;
    top:-30px;
    width:22px;
    height:22px;
    background:transparent url(tools/generationusb/presentation/images/arrow_down_black.png) no-repeat top left;
    left:20px;
    opacity:0.4;
}
.a-btn:hover{
    background:#fff;
    -webkit-box-shadow:0px 0px 9px rgba(0,0,0,0.4), 0px 0px 0px 1px rgba(188,188,188,0.1);
    -moz-box-shadow:0px 0px 9px rgba(0,0,0,0.4), 0px 0px 0px 1px rgba(188,188,188,0.1);
    box-shadow:0px 0px 9px rgba(0,0,0,0.4), 0px 0px 0px 1px rgba(188,188,188,0.1);
    text-decoration:none;
}
.a-btn:hover .a-btn-symbol{
    opacity:0;
}
.a-btn:hover .a-btn-slide-icon{
    -webkit-animation:slideDown 0.9s linear infinite;
    -moz-animation:slideDown 0.9s linear infinite;
    animation:slideDown 0.9s linear infinite;
}
.a-btn:active{
    background:#d7565b;
    -webkit-box-shadow:0px 2px 2px rgba(0,0,0,0.6) inset, 0px 0px 0px 1px rgba(188,188,188,0.1);
    -moz-box-shadow:0px 2px 2px rgba(0,0,0,0.6) inset, 0px 0px 0px 1px rgba(188,188,188,0.1);
    box-shadow:0px 2px 2px rgba(0,0,0,0.6) inset, 0px 0px 0px 1px rgba(188,188,188,0.1);
}
.a-btn:active .a-btn-text{
    color:#fff;
    text-shadow:0px 1px 1px rgba(0,0,0,0.3);
}
.a-btn:active .a-btn-slide-text{
    color:rgba(0,0,0,0.4);
    text-shadow:none;
}
@keyframes slideDown{
    0% { top: -30px; }
    100% { top: 80px;}
}
@-webkit-keyframes slideDown{
    0% { top: -30px; }
    100% { top: 80px;}
}
@-moz-keyframes slideDown{
    0% { top: -30px; }
    100% { top: 80px;}
}

	</style>';
	$size = filesize("files/".$GLOBALS['wiki']->config['generationusb']['filename'].".zip");
	$sizes = array(" Bytes", " KB", " MB", " GB", " TB", " PB", " EB", " ZB", " YB");
    $poids = round($size/pow(1024, ($i = floor(log($size, 1024)))), $i > 1 ? 2 : 0) . $sizes[$i];
	echo 	'<a href="files/'.$GLOBALS['wiki']->config['generationusb']['filename'].'.zip" class="a-btn">
				<span class="a-btn-symbol">Z</span>
				<span class="a-btn-text">T&eacute;l&eacute;charger '.$GLOBALS['wiki']->config['generationusb']['filename'].'.zip</span> 
				<span class="a-btn-slide-text">'.$poids.' - Windows - g&eacute;n&eacute;r&eacute; le '.date ("d.m.Y \a H:i:s.", filemtime('files/'.$GLOBALS['wiki']->config['generationusb']['filename'].'.zip')).'</span>
				<span class="a-btn-slide-icon"></span>
			</a><div class="clear"></div>';
	if ($this->UserIsAdmin() && $this->GetMethod()!='generationusb') {
		echo '<a href="'.$this->Href('generationusb').'" title="Mettre &agrave; jour le YesWiki portable">Mettre &agrave; jour le YesWiki portable</a>';
	}
}

else {
	echo '<div class="info_box">Pas de fichier YesWiki portable cr&eacute;&eacute; pour l\'instant.<br />';
	if ($this->UserIsAdmin()) echo '<a href="'.$this->Href('generationusb').'" title="G&eacute;n&eacute;rer le YesWiki portable">G&eacute;n&eacute;rer le YesWiki portable</a>';
	echo '</div>';
}
?>
