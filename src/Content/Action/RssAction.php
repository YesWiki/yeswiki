<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{rss}}` -- converted from the procedural actions/rss.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class RssAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'rss';
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
        $tags = $this->wiki->GetParameter('tags');
        $class = $this->wiki->GetParameter('class');
        if (empty($class)) {
            $class = '';
        }

        if ($this->wiki->GetMethod() != 'rss' && $this->wiki->GetMethod() != 'xml' && $this->wiki->GetMethod() != 'tagrss') { // on affiche un lien dans la page si on n'est pas en xml
            echo '<a class="' . $class . ' rss-icon" href="' . $this->getService(UrlFormatter::class)->href('tagrss', $this->wiki->GetPageTag(), 'tags=' . $tags) . '" title="' . _t('TAGS_RSS_FEED_FOR_NEW_PAGES_WITH_TAGS') . ' : ' . $tags . '">
        		</a>' . "\n";

            return;
        }
    }
}
