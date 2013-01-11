<?php

if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

if (!function_exists("wakkaProgressBar")) {

    function wakkaProgressBar($things)
    {  
       $val = preg_replace("#%|\[|\]#","",$things);
       return "<img src=\"tools/progressBar/libs/progressBar.php?percent=".$val[0]."\" /> ";
    }

}
$plugin_output_new = preg_replace_callback("#\[[0-9]+%\]#", "wakkaProgressBar", $plugin_output_new);

?> 

