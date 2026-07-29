<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `{{liensstyle}}` -- converted from the procedural actions/liensstyle.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class LiensstyleAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'liensstyle';
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
        // si l'action propose d'autres css à ajouter, on les ajoute
        $othercss = $this->getService(PerformableArguments::class)->get('othercss');
        if (!empty($othercss)) {
            echo $this->getService(MarkdownFormatterService::class)->format('{{linkstyle othercss="' . $othercss . '"}}');
        } else {
            echo $this->getService(MarkdownFormatterService::class)->format('{{linkstyle}}');
        }
    }
}
