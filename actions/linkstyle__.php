
<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// if exists and not empty, add the 'PageCss' yeswiki page to the styles (the PageCss content must respect the CSS syntax)
$pageCss = $this->LoadPage('PageCss');
if ($pageCss && !empty($pageCss['body'])) {
    echo '  <link rel="stylesheet" href="' . $this->href('css', 'PageCss') .'" />'."\n";
}
