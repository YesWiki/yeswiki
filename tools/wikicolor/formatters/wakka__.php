<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

if (!function_exists("wakkaColor")) {

    function wakkaColor($things)
    {  
       $text = preg_replace("#~~\(.*\)|~~#","",$things);
       $color = preg_replace("#~~\(|\).*~~#","",$things);
       return '<span class="coloredtext" style="color:'.$color[0].'">'.$text[0].'</span>';
    }

}
$plugin_output_new = preg_replace_callback("#~~\(.*\).*~~#", "wakkaColor", $plugin_output_new);

?>