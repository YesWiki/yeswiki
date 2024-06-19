<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$res = $this->Action('backlinks');
echo $this->Header();
echo "<div class=\"page\" style=\"padding: 1em\">\n";
echo $res;
echo "\n</div>\n";
echo $this->Footer();

?> 