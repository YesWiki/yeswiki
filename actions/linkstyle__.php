
<?php
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// This GLOBALS is populated from AddCSS and AddCSSFile, we add it at the end
// Be careful to render Header AFTER rendering actions
// do not use YesWiki:AddCSSFile(), YesWiki:LinkCSSFile() or YesWiki:AddCSS() in custom/linkstyle__.php (it will not work)
if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
    echo $GLOBALS['css'];
    // empty $GLOBALS['css'] to fill it with other calls to AddCSS flushed in linkjavascript.php
    $GLOBALS['css'] = '';
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
