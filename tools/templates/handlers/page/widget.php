<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

$width = $_GET['width'] ?? '100%';
$height = $_GET['height'] ?? 700;

// sanitize
$width = (preg_match('/^[0-9]+(%|[a-z]{2})?$/m', $width)) ? $width : '100%';
$height = (preg_match('/^[0-9]+(%|[a-z]{2})?$/m', $height)) ? $height : 700;

echo $this->Header();
echo '<h2>' . _t('TEMPLATE_WIDGET_TITLE') . '</h2>';
echo "<pre>\n";
echo htmlentities('<iframe class="yeswiki_frame" width="' . $width . '" height="' . $height . '" frameborder="0" src="' . $this->Href('bazariframe') . '"></iframe>') . "\n";
echo '</pre>' . "\n";
echo '<div class="alert alert-info">' . _t('TEMPLATE_WIDGET_COPY_PASTE') . '</div>' . "\n";
echo '<iframe class="yeswiki_frame" width="' . $width . '" height="' . $height . '" frameborder="0"></iframe>';
echo $this->Footer();
