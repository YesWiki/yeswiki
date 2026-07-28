<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{progressbar}}` -- converted from the procedural actions/progressbar.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ProgressbarAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'progressbar';
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
        // valeur de la progressbar
        $val = $this->wiki->GetParameter('val');
        if (empty($val)) {
            $error = ' ' . _t('PROGRESSBAR_REQUIRED_VAL_PARAM');
        } elseif (!is_numeric($val) || $val < 0 || $val > 100) {
            $error = ' ' . _t('PROGRESSBAR_ERROR_VAL_PARAM');
        }

        // classe css supplémentaire pour changer le look
        $class = $this->wiki->GetParameter('class');
        $class = 'yw-progressbar ' . $class;

        if (isset($error)) {
            echo '<div class="yw-alert yw-alert--danger">
                <strong>Action {{progressbar ..}}</strong> : ' . $error . '
              </div>' . "\n";
        } else {
            echo '<div class="' . $class . '">
            <div class="yw-progressbar__bar" role="progressbar"
            style="width: ' . $val . '%;"
            aria-valuenow="' . $val . '" aria-valuemin="0" aria-valuemax="100"></div>
            </div>' . "\n";
        }
    }
}
