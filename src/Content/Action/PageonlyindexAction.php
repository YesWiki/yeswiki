<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageType;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `{{pageonlyindex}}` -- converted from the procedural actions/pageonlyindex.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class PageonlyindexAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'pageonlyindex';
    }

    public function components(): array
    {
        return [
            Component::for('pageonlyindex')
                ->category(Category::Navigation)
                ->label(_t('AB_advanced_action_pageonlyindex_label'))
                ->icon('list-details')
                ->previewHeight('200px'),
        ];
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

    /**
     * Every page of the wiki except the entries -- which is what the name has always meant.
     *
     * It asked that question as `body NOT LIKE '{"%'`: an entry's body was JSON and a page's
     * was wiki markup, so "does it start with a brace" separated them. Ticket 09 made *every*
     * body JSON and the predicate became universally false -- `{{pageonlyindex}}` has listed
     * nothing at all since, on every wiki, silently, because an index with no entries in it
     * looks exactly like an empty index. Measured on a real install: 139 pages, 0 listed.
     *
     * The `type` column (ticket 27) is what the question is actually about, and it survives a
     * body that changes shape again.
     */
    private function emit(): void
    {
        $db = $this->getService(DbService::class);
        $pages = $db->loadAll(
            'SELECT tag FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . 'pages'
            . " WHERE latest = 'Y' AND parent = '' AND " . $db->quoteIdentifier('type') . ' <> ?'
            . ' ORDER BY tag',
            [PageType::ENTRY]
        );
        if ($pages) {
            foreach ($pages as $page) {
                // XXX: strtoupper is locale dependent
                $firstChar = strtoupper($page['tag'][0]);
                if (!preg_match('/' . WN_UPPER . '/', $firstChar)) {
                    $firstChar = '#';
                }

                if (empty($curChar) || $firstChar != $curChar) {
                    if (!empty($curChar)) {
                        echo "<br />\n";
                    }
                    echo "<b>$firstChar</b><br />\n";
                    $curChar = $firstChar;
                }

                echo $this->getService(LinkRenderer::class)->linkToPage($page['tag'], '', ''),"<br />\n";
            }
        } else {
            echo '<i>' . _t('NO_PAGE_FOUND') . '.</i>';
        }
    }
}
