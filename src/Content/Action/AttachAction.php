<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Attach;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{attach}}` -- converted from the procedural actions/attach.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class AttachAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'attach';
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
        // {{attach}} action (ticket 17, relocated from tools/attach/actions/attach.php).
        // `file=`/`attachfile=` is a FileManager file-entry tag (see src/Attach.php's
        // CheckParams()); see docs/actions/attach.yaml for the full argument list.

        $att = new Attach($this->services);
        $att->doAttach();
        unset($att);
    }
}
