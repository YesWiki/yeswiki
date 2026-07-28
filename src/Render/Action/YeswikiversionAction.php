<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{yeswikiversion}}` -- converted from the procedural actions/yeswikiversion.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class YeswikiversionAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'yeswikiversion';
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
        echo '<div class="yw-text-center">(>^_^)> ' . _t('RUNNING_WITH') . ' <a title="' . $this->wiki->config['yeswiki_version'] . ' ' . $this->wiki->config['yeswiki_release'] . '" href="https://www.yeswiki.net">YesWiki</a> <(^_^<)</div>' . "\n";
    }
}
