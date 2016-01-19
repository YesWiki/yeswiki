<?php

/*
*/

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->HasAccess("read")) {
    if (!$this->page) {
        return;
    } else {
        // affichage de la page formatee
        echo "<div class=\"page\">\n" . $this->Format($this->page["body"]) . "\n</div>\n";
    }
} else {
    return;
}
