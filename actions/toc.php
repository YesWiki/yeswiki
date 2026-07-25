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

if (!function_exists('translate2toc')) {
    /**
     * Parses $text with the same CommonMark engine the wakka formatter uses, and builds
     * the <li> list of headings. Each heading gets the same "TOC_{level}_{n}" id that
     * tools/toc/formatters/wakka__.php assigns to the matching rendered <hN> tag.
     */
    function translate2toc($text)
    {
        $environment = new League\CommonMark\Environment\Environment();
        $environment->addExtension(new League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension());
        $environment->addExtension(new League\CommonMark\Extension\GithubFlavoredMarkdownExtension());
        $document = (new League\CommonMark\Parser\MarkdownParser($environment))->parse($text);

        $output = '';
        $counters = [];
        foreach ($document->iterator() as $node) {
            if (!$node instanceof League\CommonMark\Extension\CommonMark\Node\Block\Heading) {
                continue;
            }
            $level = $node->getLevel();
            $counters[$level] = ($counters[$level] ?? 0) + 1;
            $toc = 'TOC_' . $level . '_' . $counters[$level];

            $title = '';
            foreach ($node->iterator() as $inline) {
                if ($inline instanceof League\CommonMark\Node\StringContainerInterface) {
                    $title .= $inline->getLiteral();
                } elseif ($inline instanceof League\CommonMark\Node\Inline\Newline) {
                    $title .= ' ';
                }
            }

            $output .= '<li class="toc' . $level . '"><a href="#' . $toc . '">'
                . htmlspecialchars(trim($title), ENT_COMPAT, YW_CHARSET) . "</a></li>\n";
        }

        return $output;
    }
}

// on vérifie qu'il y est au moins un titre pour faire la liste
$tocList = translate2toc($toc_body);
if ($tocList !== '') {
    echo "<ul class=\"yw-list-unstyled\">\n" . $tocList . "</ul>\n";
}

// on ferme les divs ouvertes par l'action toc ; the box's scroll-follow behavior is pure
// CSS now (see .yw-toc's position: sticky rule in yw-core.css), no JS needed.
echo "</div><!-- /#$collapseId -->\n
    </div><!-- /#toc" . $tag . " -->\n";
