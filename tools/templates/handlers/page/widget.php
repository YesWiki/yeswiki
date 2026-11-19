<?php

$isDimension = fn ($value) => is_string($value) && preg_match('/\A[0-9]+(%|[a-z]{2})?\z/', $value) === 1;
$width = $isDimension($_GET['width'] ?? null) ? $_GET['width'] : '100%';
$height = $isDimension($_GET['height'] ?? null) ? $_GET['height'] : '700';

$frame = '<iframe class="yeswiki_frame" width="' . htmlspecialchars($width, ENT_QUOTES) . '" height="' . htmlspecialchars($height, ENT_QUOTES) . '" frameborder="0"';

echo $this->Header();
echo '<h2>' . _t('TEMPLATE_WIDGET_TITLE') . '</h2>';
echo "<pre>\n";
echo htmlspecialchars($frame . ' src="' . $this->Href('bazariframe') . '"></iframe>', ENT_QUOTES) . "\n";
echo '</pre>' . "\n";
echo '<div class="alert alert-info">' . _t('TEMPLATE_WIDGET_COPY_PASTE') . '</div>' . "\n";
echo $frame . '></iframe>';
echo $this->Footer();
