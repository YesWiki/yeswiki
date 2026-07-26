
<?php

// relocated from tools/tags/actions/linkstyle__.php (ticket 10): tags.css covers the
// tag cloud, epub export selection UI, and the rss-icon, loaded on every page like the
// rest of this file's styles
$this->AddCSSFile('styles/actions/tags-nuage.css');

// relocated from tools/attach/actions/linkstyle__.php (ticket 17): attached-file display
// (figures/captions/zoom), pointimage markers, background-image, embedded pdf/video sizing.
// Trimmed of qq-uploader (dead, superseded by the /api/files upload route) and Bootstrap
// .popover/jPlayer rules (converted to yw-popover / native <audio>/<video>).
$this->AddCSSFile('styles/actions/attach.css');

// relocated from tools/bazar/actions/linkstyle__.php (ticket 24): styles for bazar entries,
// the calendar view, and the google/leaflet map display.
// TODO(ticket 24 checkpoint 2): update to styles/actions/bazar.css once bazar.css itself
// is relocated there alongside the rest of ticket 24's JS/CSS conversion pass.
$this->AddCSSFile('tools/bazar/presentation/styles/bazar.css');

// if exists and not empty, add the 'PageCss' yeswiki page's content to the styles
// (the PageCss content must respect the CSS syntax). Inlined via AddCSS() rather than
// linked from the /raw handler: raw.php serves text/plain, and browsers in standards
// mode reject a <link rel="stylesheet"> whose response isn't text/css.
$pageCss = $this->LoadPage('PageCss');
if ($pageCss && !empty($pageCss['body'])) {
    $this->AddCSS($pageCss['body']);
}

// This GLOBALS is populated from AddCSS and AddCSSFile, we add it at the end
// Be careful to render Header AFTER rendering actions
// do not use YesWiki:AddCSSFile(), YesWiki:LinkCSSFile() or YesWiki:AddCSS() in custom/linkstyle__.php (it will not work)
if (isset($GLOBALS['css']) && !empty($GLOBALS['css'])) {
    echo $GLOBALS['css'];
    // empty $GLOBALS['css'] to fill it with other calls to AddCSS flushed in linkjavascript.php
    $GLOBALS['css'] = '';
}
