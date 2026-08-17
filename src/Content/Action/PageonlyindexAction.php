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

/** `{{pageonlyindex}}` -- converted from the procedural actions/pageonlyindex.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    /** Every page of the wiki except the entries -- which is what the name has always meant. */
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
