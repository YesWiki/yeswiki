<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{lang}}` -- converted from the procedural actions/lang.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class LangAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'lang';
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
        // {{lang="xx"}} markers split a page body into per-language sections; the
        // show/iframe handlers and {{include}} strip them before rendering (see
        // src/lang.functions.php, formerly tools/lang). This deliberately-empty action
        // keeps a marker that still reaches the formatter (revisions, exports, a page
        // with no matching section) from rendering an "unknown action" error.
    }
}
