<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}
if ($this->GetMethod() != 'show') {
    echo '<meta name="robots" content="noindex, nofollow">'."\n";
} elseif (isset($this->config['meta']['robots'])) {
    echo '<meta name="robots" content="'.$this->config['meta']['robots'].'">'."\n";
}
