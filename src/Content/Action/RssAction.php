<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{rss}}` -- converted from the procedural actions/rss.php by ticket 06. */
class RssAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'rss';
    }

    public function components(): array
    {
        return [
            Component::for('rss')
                ->category(Category::Lists)
                ->label(_t('AB_tags_listpagestag_rss_label'))
                ->icon('rss')
                ->previewHeight('200px')
                ->settings(
                    Setting::text('tags')
                        ->label(_t('AB_tags_listpagestag_tags_label'))
                        ->hint(_t('AB_tags_listpagestag_tags_hint')),
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
        $tags = $this->getService(PerformableArguments::class)->get('tags');
        $class = $this->getService(PerformableArguments::class)->get('class');
        if (empty($class)) {
            $class = '';
        }

        if ($this->getService(PageContext::class)->getMethod() != 'rss' && $this->getService(PageContext::class)->getMethod() != 'xml' && $this->getService(PageContext::class)->getMethod() != 'tagrss') {
            echo '<a class="' . $class . ' rss-icon" href="' . $this->getService(UrlFormatter::class)->href('tagrss', $this->getService(PageContext::class)->getTag(), 'tags=' . $tags) . '" title="' . _t('TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS') . ' : ' . $tags . '">
        		</a>' . "\n";

            return;
        }
    }
}
