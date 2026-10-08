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
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;

/** `{{toc}}` -- converted from the procedural actions/toc.php by ticket 06. */
class TocAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    private const DISPLAYS = ['block', 'rail', 'graduated-rail'];

    public static function performableName(): string
    {
        return 'toc';
    }

    /** The palette entry for `{{toc}}`. */
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
                    Setting::choice('display', [
                        'block' => _t('AB_toc_display_block'),
                        'rail' => _t('AB_toc_display_rail'),
                        'graduated-rail' => _t('AB_toc_display_graduated_rail'),
                    ])
                        ->label(_t('AB_toc_display_label'))
                        ->hint(_t('AB_toc_display_hint'))
                        ->default('block')
                        ->half(),
                    Setting::checkbox('closed')
                        ->title(_t('AB_toc_closed_title'))
                        ->label(_t('AB_toc_closed_label'))
                        ->checkedValues('1', '')
                        ->default('')
                        ->showIf(['display' => 'block'])
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
        $arguments = $this->getService(PerformableArguments::class);
        $class = $arguments->get('class');
        $chosenTitle = (string)$arguments->get('title');
        $title = $chosenTitle !== '' ? $chosenTitle : _t('TOC_TABLE_OF_CONTENTS');
        $display = in_array($arguments->get('display'), self::DISPLAYS, true) ? $arguments->get('display') : 'block';

        $tocList = '';
        foreach ($this->getService(\YesWiki\Render\Service\MarkdownFormatterService::class)->headings($toc_body) as $heading) {
            $tocList .= '<li class="toc' . $heading['level'] . '"><a href="#' . htmlspecialchars($heading['id'], ENT_COMPAT, YW_CHARSET) . '">'
                . htmlspecialchars($heading['title'], ENT_COMPAT, YW_CHARSET) . "</a></li>\n";
        }

        if ($display === 'block') {
            $this->emitBlock($tag, $title, $class, $arguments->get('closed') == 1, $tocList);
        } else {
            $this->emitRail($tag, $title, $chosenTitle !== '', $class, $display === 'graduated-rail', $tocList);
        }
    }

    /** The folding box, the historical rendering. */
    private function emitBlock(string $tag, string $title, ?string $class, bool $closed, string $tocList): void
    {
        $collapseId = 'toc-menu' . $tag;

        echo '<div id="toc' . $tag . '" class="yw-toc' . (!empty($class) ? ' ' . $class : '') . "\">\n";

        echo '<details class="yw-accordion__item"' . ($closed ? '' : ' open') . ">\n"
            . '<summary class="yw-accordion__summary yw-toc__title"><strong>' . $title . "</strong></summary>\n"
            . "<div id=\"$collapseId\" class=\"yw-accordion__body yw-toc__menu\">\n";

        if ($tocList !== '') {
            echo "<ul class=\"yw-list-unstyled\">\n" . $tocList . "</ul>\n";
        }

        echo "</div><!-- /#$collapseId -->\n</details>\n"
            . '</div><!-- /#toc' . $tag . " -->\n";
    }

    /** A rail fixed to the right edge that follows the reading position, with sections sized to their length when graduated. */
    private function emitRail(string $tag, string $title, bool $showTitle, ?string $class, bool $graduated, string $tocList): void
    {
        if ($tocList === '') {
            return;
        }

        $this->getService(AssetRegistry::class)->addJsFile('javascripts/toc-rail.js');

        echo '<nav id="toc' . $tag . '" class="yw-toc yw-toc--rail' . ($graduated ? ' yw-toc--graduated' : '')
            . (!empty($class) ? ' ' . $class : '') . '" aria-label="' . htmlspecialchars(strip_tags($title), ENT_COMPAT, YW_CHARSET) . '" data-yw-toc-rail>' . "\n"
            . ($showTitle ? '<div class="yw-toc__heading">' . $title . "</div>\n" : '')
            . '<span class="yw-toc__marker" aria-hidden="true"></span>' . "\n"
            . "<ul class=\"yw-list-unstyled yw-toc__list\">\n" . $tocList . "</ul>\n"
            . '</nav><!-- /#toc' . $tag . " -->\n";
    }
}
