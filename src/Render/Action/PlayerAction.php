<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;

/**
 * `{{player}}` -- converted from the procedural actions/player.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class PlayerAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'player';
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
        // {{player}} action (ticket 17, relocated from tools/attach/actions/player.php).
        // jPlayer (jQuery-based, with a Flash fallback via swfPath) converted to plain HTML5
        // <audio>/<video> per ADR-0005 -- native browser controls replace the whole custom
        // button/progress-bar markup jPlayer built. The FreeMind (.mm) branch is gone entirely:
        // it embedded a Flash .swf, unsupported in any browser since ~2021.

        $url = $this->getService(PerformableArguments::class)->get('url');
        $type = $this->getService(PerformableArguments::class)->get('type');

        if (!empty($url)) {
            $height = $this->getService(PerformableArguments::class)->get('height');
            if (empty($height)) {
                $height = '300px';
            }

            $width = $this->getService(PerformableArguments::class)->get('width');
            if (empty($width)) {
                $width = '400px';
            }

            // {{attach}}'s own callers (Attach::showAsAudio()/showAsVideo()) always pass an
            // explicit type= -- the URL itself has no extension once it's an /api/files/{tag}/
            // download route. The extension sniff below is only a fallback for any other direct
            // {{player url="raw/file/path.ext"}} caller.
            $extension = strtolower(substr(strrchr($url, '.'), 1));
            if ($type == 'audio' || $extension == 'mp3' || $extension == 'm4a') {
                echo '<audio controls style="width:100%;" src="' . htmlspecialchars($url) . '">'
                    . '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>'
                    . '</audio>';
            } elseif ($type == 'video' || in_array($extension, ['webm', 'mp4', 'ogg', 'flv'], true)) {
                echo '<video controls style="max-width:100%;width:' . htmlspecialchars($width) . ';height:' . htmlspecialchars($height) . ';" src="' . htmlspecialchars($url) . '">'
                    . '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a>'
                    . '</video>';
            } else {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_PLAYER') . '</strong> : ' . _t('ATTACH_PLAYER_CAN_ONLY_OPEN_FILES_LIKE') . ' (' . $url . ') ' . _t('ATTACH_NOT_LINKED_TO_GOOD_FILE_EXTENSION') . '.</div>' . "\n";
            }
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('ATTACH_ACTION_PLAYER') . '</strong> : '
                . _t('ATTACH_PARAM_URL_REQUIRED') . '.</div>' . "\n";
        }
    }
}
