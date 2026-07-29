<?php

namespace YesWiki\Content\Action;

use YesWiki\Content\Attach;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{filemanager}}` -- converted from the procedural actions/filemanager.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class FilemanagerAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'filemanager';
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
        // {{filemanager}} action (ticket 17, relocated from tools/attach/actions/filemanager.php).
        // Manages files linked via the {{attach}} action. Requires actions/attach.php.

        if ($this->getService(AclService::class)->hasAccess('write')) {
            $att = new Attach($this->wiki);
            $att->doFileManagerAction();
            unset($att);
        } else {
            echo '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER') . '.</div>' . "\n";
        }
    }
}
