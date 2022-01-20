
<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// if exists and not empty, add the 'PageCss' yeswiki page to the styles
// (the PageCss content must respect the CSS syntax)
// Same reason than for GLOBALS, we include it so we are sure it's included at the end
$pageCss = $this->LoadPage('PageCss');
if ($pageCss && !empty($pageCss['body'])) {
    echo <<<HTML
        <link rel="stylesheet" href="{$this->href('css', 'PageCss')}" />
    HTML;
}
