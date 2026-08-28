<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Database\SqlFragment;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Render\Service\LinkRenderer;

/** `{{pageindex}}` -- converted from the procedural actions/pageindex.php by ticket 06. */
class PageindexAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'pageindex';
    }

    public function components(): array
    {
        return [
            Component::for('pageindex')
                ->category(Category::Navigation)
                ->label(_t('AB_advanced_action_pageindex_label'))
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

    private function emit(): void
    {
        $readable = $this->readableFilter();
        $sql = 'SELECT tag FROM ' . $this->getService(RuntimeConfig::class)['table_prefix'] . "pages WHERE latest = 'Y' AND parent=''"
            . ($readable->isEmpty() ? '' : ' AND ' . $readable->sql)
            . ' ORDER BY tag';
        if ($pages = $this->getService(DbService::class)->loadAll($sql, $readable->params)) {
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

    private function readableFilter(): SqlFragment
    {
        return $this->getService(AclService::class)->readableFilter();
    }
}
