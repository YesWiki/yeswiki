<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

/*if (!function_exists("wakkaWikiProgressBar")) {

    function wakkaWikiProgressBar($things)
    {  
       $val = preg_replace("#%|\[|\]#","",$things);
       return "<img src=\"tools/wikiProgressBar/php/progressBar.php?percent=".$val[0]."\" /> ";
    }

}*/

$patterns = array(
    "#\:\)|\:\-\)#",
    "#\;\)|\;\-\)#",
    "#\:\(|\:\-\(#",
    "#8\)|8\-\)#",
    "#\:D|\:\-D|\:d|\:\-d#",
    "#\:o|\:\-o|\:O|\:\-O#",
);

$replacements = array(
    "<img src=\"tools/smileys/img/icon_e_smile.gif\" />",
    "<img src=\"tools/smileys/img/icon_e_wink.gif\" />",
    "<img src=\"tools/smileys/img/icon_e_sad.gif\" />",
    "<img src=\"tools/smileys/img/icon_cool.gif\" />",
    "<img src=\"tools/smileys/img/icon_e_biggrin.gif\" />",
    "<img src=\"tools/smileys/img/icon_e_surprised.gif\" />",
); 



$plugin_output_new = preg_replace($patterns, $replacements, $plugin_output_new);

?> 

