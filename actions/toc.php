<?php

$GLOBALS['tocaction'] = 0;

$tag = $this->GetPageTag();
$page = $this->LoadPage($tag);
$toc_body = $page['body'] ?? '';
$class = $this->GetParameter('class');
$closed = $this->GetParameter('closed');
$title = $this->GetParameter('title');
if (empty($title)) {
    $title = _t('TOC_TABLE_OF_CONTENTS');
}

$collapseId = 'toc-menu' . $tag;
$expanded = ($closed == 1) ? 'false' : 'true';

echo '<div id="toc' . $tag . '" class="yw-toc' . (!empty($class) ? ' ' . $class : '') . "\">\n";

echo '<div class="yw-toc__title yw-collapse-toggle" data-yw-collapse-toggle="#' . $collapseId . '"'
    . ' aria-expanded="' . $expanded . '" aria-controls="' . $collapseId . '">'
    . '<span class="yw-dropdown__caret yw-collapse-toggle__caret"></span>&nbsp;<strong>' . $title . "</strong>
</div><!-- /.yw-toc__title -->\n
<div id=\"$collapseId\" class=\"yw-toc__menu yw-collapse" . ($closed == 1 ? '' : ' yw-collapse--open') . "\">\n";

// Heading ids come from the same CommonMark environment that renders the page, so the
// links here and the anchors in the output cannot drift apart. Ticket 06 removed the
// previous arrangement -- a counter in formatters/wakka__.php regexing the rendered HTML,
// mirrored by a second counter in a translate2toc() defined here -- which desynced as soon
// as any action emitted its own <hN>.
$tocList = '';
foreach ($this->services->get(\YesWiki\Render\Service\MarkdownFormatterService::class)->headings($toc_body) as $heading) {
    $tocList .= '<li class="toc' . $heading['level'] . '"><a href="#' . htmlspecialchars($heading['id'], ENT_COMPAT, YW_CHARSET) . '">'
        . htmlspecialchars($heading['title'], ENT_COMPAT, YW_CHARSET) . "</a></li>\n";
}
if ($tocList !== '') {
    echo "<ul class=\"yw-list-unstyled\">\n" . $tocList . "</ul>\n";
}

// on ferme les divs ouvertes par l'action toc ; the box's scroll-follow behavior is pure
// CSS now (see .yw-toc's position: sticky rule in yw-core.css), no JS needed.
echo "</div><!-- /#$collapseId -->\n
    </div><!-- /#toc" . $tag . " -->\n";
