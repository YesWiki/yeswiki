<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\UrlFormatter;

/** `{{translation}}` -- converted from the procedural actions/translation.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $destination = $this->getService(PerformableArguments::class)->get('destination');
        if (empty($destination)) {
            echo _t('LANG_DESTINATION_REQUIRED');
        }

        $flagfile = 'styles/lang/flags/' . $destination . '.png';

        if (!$this->getService(ProgramFiles::class)->exists($flagfile)) {
            $img = $destination;
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

        if (isset($_GET['lang'])) {
            $previousLang = $_GET['lang'];
            unset($_GET['lang']);
        }

        echo '<a href="' . $this->getService(UrlFormatter::class)->href($wikireq === $currentTag ? '' : $this->getService(PageContext::class)->getRawMethod(), $currentTag, $queries, false) . '">' . $img . '</a>';

        if (isset($previousLang)) {
            $_GET['lang'] = $previousLang;
            unset($previousLang);
        }
    }
}
