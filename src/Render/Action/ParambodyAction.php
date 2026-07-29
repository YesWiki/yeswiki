<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\FlashMessageService;

/**
 * `{{parambody}}` -- converted from the procedural actions/parambody.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ParambodyAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'parambody';
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
        // attributs du body
        $toastDuration = !empty($this->wiki->config['toast_duration']) ? $this->wiki->config['toast_duration'] : '3000';
        $toastClass = !empty($this->wiki->config['toast_class']) ? $this->wiki->config['toast_class'] : 'alert alert-secondary-1';
        $body_attr = ($message = $this->getService(FlashMessageService::class)->getMessage()) ? "onload=\"toastMessage('" . addslashes($message) . "', " . $toastDuration . ", '" . $toastClass . "');\" " : '';
        echo $body_attr;
    }
}
