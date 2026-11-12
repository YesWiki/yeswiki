<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\TranslatableContent;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TableOfContents;

/** `{{toc}}` -- converted from the procedural actions/toc.php by ticket 06. */
class TocAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
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
        $pageManager = $this->getService(PageManager::class);
        $page = $pageManager->getOne($tag);
        $translatable = $this->getService(TranslatableContent::class);
        $toc_body = PageBody::content($translatable->forReader(
            is_array($page['body'] ?? null) ? $page['body'] : [],
            $translatable->sourceLanguageOfPage($pageManager->getMetadata($tag))
        ));
        $arguments = $this->getService(PerformableArguments::class);
        $class = $arguments->get('class');
        $chosenTitle = (string)$arguments->get('title');
        $title = $chosenTitle !== '' ? $chosenTitle : _t('TOC_TABLE_OF_CONTENTS');
        $display = $arguments->get('display');

        echo $this->getService(TableOfContents::class)->render(
            $this->getService(MarkdownFormatterService::class)->headings($toc_body),
            $tag,
            (string)$display,
            $title,
            $chosenTitle !== '',
            (string)$class,
            $arguments->get('closed') == 1
        );
    }
}
