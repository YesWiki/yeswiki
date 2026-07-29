<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Render\Service\TemplateHelperService;

/**
 * `{{end}}` -- converted from the procedural actions/end.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class EndAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'end';
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
        require_once YESWIKI_SOURCE_DIR . '/src/YesWikiPerformable.php';

        // classe css supplémentaire
        $elem = $this->getService(PerformableArguments::class)->get('elem');
        if (empty($elem)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('TEMPLATE_ACTION_END') . '</strong> : ' . _t('TEMPLATE_ELEM_PARAMETER_REQUIRED') . '.</div>' . "\n";

            return;
        }
        $pagetag = $this->getService(PageContext::class)->getTag();
        $body = isset($this->getService(PageContext::class)->getPage()['body']) ? $this->getService(PageContext::class)->getPage()['body'] : '';
        // teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
        if (!isset($GLOBALS['check_' . $pagetag])) {
            $GLOBALS['check_' . $pagetag] = [];
        }
        if (!isset($GLOBALS['check_' . $pagetag][$elem])) {
            $GLOBALS['check_' . $pagetag][$elem] = $this->getService(TemplateHelperService::class)->checkGraphicalElements($elem, $pagetag, $body);
        }

        if ($GLOBALS['check_' . $pagetag][$elem] || in_array($elem, ['tab', 'tabs'], true)) {
            echo $this->getService(Performer::class)->run($elem, 'action', [], true);
        }
    }
}
