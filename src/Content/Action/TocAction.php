<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;

/** `{{toc}}` -- converted from the procedural actions/toc.php by ticket 06. */
class TocAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'toc';
    }

    /**
     * `{{toc}}` had no YAML palette entry -- it was one of the fifty-one actions the palette never listed, so the only way to put a table of contents on a page was to know the tag and type it.
     */
    public function components(): array
    {
        return [
            Component::for('toc')
                ->category(Category::Navigation)
                ->label(_t('AB_toc_label'))
                ->icon('list-details')
                ->description(_t('AB_toc_description'))
                ->previewHeight('250px')
                ->settings(
                    Setting::text('title')
                        ->label(_t('AB_toc_title_label'))
                        ->hint(_t('AB_toc_title_hint'))
                        ->half(),
                    Setting::checkbox('closed')
                        ->title(_t('AB_toc_closed_title'))
                        ->label(_t('AB_toc_closed_label'))
                        ->checkedValues('1', '')
                        ->default('')
                        ->half(),
                    Setting::cssClass('class')
                        ->label(_t('AB_template_actions_class'))
                        ->full(),
                ),
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
        $tag = $this->getService(PageContext::class)->getTag();
        $page = $this->getService(PageManager::class)->getOne($tag);
        $toc_body = PageBody::content($page['body'] ?? []);
        $class = $this->getService(PerformableArguments::class)->get('class');
        $closed = $this->getService(PerformableArguments::class)->get('closed');
        $title = $this->getService(PerformableArguments::class)->get('title');
        if (empty($title)) {
            $title = _t('TOC_TABLE_OF_CONTENTS');
        }

        $collapseId = 'toc-menu' . $tag;

        echo '<div id="toc' . $tag . '" class="yw-toc' . (!empty($class) ? ' ' . $class : '') . "\">\n";

        echo '<details class="yw-accordion__item"' . ($closed == 1 ? '' : ' open') . ">\n"
            . '<summary class="yw-accordion__summary yw-toc__title"><strong>' . $title . "</strong></summary>\n"
            . "<div id=\"$collapseId\" class=\"yw-accordion__body yw-toc__menu\">\n";

        $tocList = '';
        foreach ($this->getService(\YesWiki\Render\Service\MarkdownFormatterService::class)->headings($toc_body) as $heading) {
            $tocList .= '<li class="toc' . $heading['level'] . '"><a href="#' . htmlspecialchars($heading['id'], ENT_COMPAT, YW_CHARSET) . '">'
                . htmlspecialchars($heading['title'], ENT_COMPAT, YW_CHARSET) . "</a></li>\n";
        }
        if ($tocList !== '') {
            echo "<ul class=\"yw-list-unstyled\">\n" . $tocList . "</ul>\n";
        }

        echo "</div><!-- /#$collapseId -->\n</details>\n"
            . '</div><!-- /#toc' . $tag . " -->\n";
    }
}
