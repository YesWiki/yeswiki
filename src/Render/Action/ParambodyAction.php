<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

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
        // Ticket 16: this used to deliver flash messages as `onload="toastMessage(...)"` on
        // <body>. A boosted navigation swaps the body's *contents*, so the attribute was
        // neither replaced nor re-fired and the message was simply lost. Flash now renders
        // inside the body block via `{{ page_state() }}` and is picked up by yw-flash.js,
        // which works on a full load and on a swap alike -- and is reachable by a test, which
        // a `<body onload>` attribute never was.
        //
        // The action stays: a squelette calls it for body attributes, and themes may add more.
        echo '';
    }
}
