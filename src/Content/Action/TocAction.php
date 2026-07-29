<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;

/**
 * `{{toc}}` -- converted from the procedural actions/toc.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class TocAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'toc';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $GLOBALS['tocaction'] = 0;

        $tag = $this->getService(PageContext::class)->getTag();
        $page = $this->getService(PageManager::class)->getOne($tag);
        $toc_body = $page['body'] ?? '';
        $class = $this->getService(PerformableArguments::class)->get('class');
        $closed = $this->getService(PerformableArguments::class)->get('closed');
        $title = $this->getService(PerformableArguments::class)->get('title');
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
        foreach ($this->getService(\YesWiki\Render\Service\MarkdownFormatterService::class)->headings($toc_body) as $heading) {
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
    }
}
