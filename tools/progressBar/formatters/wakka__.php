<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if (!function_exists('wakkaProgressBar')) {
    function wakkaProgressBar($things)
    {
        return '<img loading="lazy" class="progressbar" alt="' . $things[1] . '%" src="tools/progressBar/libs/progressBar.php?percent=' . $things[1] . '" /> ';
    }
}

$plugin_output_new = preg_replace_callback("/\[([0-9]+)%\]/msu", 'wakkaProgressBar', $plugin_output_new);
