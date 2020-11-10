<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$title = htmlspecialchars(getTitleFromBody($this->page), ENT_COMPAT | ENT_HTML5);
if ($title) {
    echo $title;
} else {
    echo $this->GetPageTag();
}
