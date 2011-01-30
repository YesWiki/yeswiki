<?php
/* $Id: svg.php 845 2007-08-23 19:10:25Z lordfarquaad $
 * 
 * Description : Handler pour affichage SVG
 * auteurs : Yann Le Guennec - Charles Nepote
 * version 0.4
 * 26.07.2007
 * 
 * Licence GPL
 */

// Mime type pour le SVG
header("Content-type: image/svg+xml");

if(isset($_GET['svg'])) $svg = $_GET['svg'];
else $svg = "reseaupagecourante";
if(preg_match("/^[a-z0-9]*$/",$svg))
{
    $url = $this->config["handler_path"]."/page/svg/".$svg.".php";
    if(is_file($url))
    {
        include($url);
    } 
}
?>
