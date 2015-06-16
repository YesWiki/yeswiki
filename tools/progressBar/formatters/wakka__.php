<?php
if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if (!function_exists("wakkaProgressBar")) {

    function wakkaProgressBar($things)
    {  
       return '<img class="progressbar" src="tools/progressBar/libs/progressBar.php?percent='.$things[1].'" /> ';
    }

}

$plugin_output_new = preg_replace_callback("#\[([0-9]+)%\]#", "wakkaProgressBar", $plugin_output_new);


