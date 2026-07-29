<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `{{translation}}` -- converted from the procedural actions/translation.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class TranslationAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'translation';
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
        // {{translation destination="xx"}} (formerly tools/lang): renders a flag link
        // that switches the current page to the destination language via ?lang=xx

        $destination = $this->getService(PerformableArguments::class)->get('destination');
        if (empty($destination)) {
            echo _t('LANG_DESTINATION_REQUIRED');
        }

        $flagfile = 'styles/lang/flags/' . $destination . '.png';

        if (!file_exists($flagfile)) {
            $img = $destination; // we are using the iso code if no flag available
        } else {
            $img = '<img loading="lazy" src="' . $flagfile . '" title="' . $destination . '" alt="' . $destination . ' language">';
        }

        $wikireq = $_GET['wiki'] ?? null;

        $currentMethod = $this->getService(PageContext::class)->getRawMethod() === '' ? '' : '/' . $this->getService(PageContext::class)->getRawMethod();
        $currentTag = (strpos($wikireq, '/') !== false)
                ? substr($wikireq, 0, -strlen($currentMethod))
                : $wikireq;

        $queries = [];
        parse_str($_SERVER['QUERY_STRING'], $queries);
        unset($queries[$wikireq]);
        unset($queries['wiki']);
        $queries['lang'] = $destination;

        // remove $_GET['lang'] because it is used by Href
        if (isset($_GET['lang'])) {
            $previousLang = $_GET['lang'];
            unset($_GET['lang']);
        }
        // Todo : utiliser template
        echo '<a href="' . $this->getService(UrlFormatter::class)->href($wikireq === $currentTag ? '' : $this->getService(PageContext::class)->getRawMethod(), $currentTag, $queries, false) . '">' . $img . '</a>';

        if (isset($previousLang)) {
            $_GET['lang'] = $previousLang;
            unset($previousLang);
        }
    }
}
