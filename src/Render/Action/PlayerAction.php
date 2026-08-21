<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PerformableArguments;

/** `{{player}}` -- converted from the procedural actions/player.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
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

            // strrchr() gives false for a URL with no dot in it at all
            $lastDot = strrchr($url, '.');
            $extension = $lastDot === false ? '' : strtolower(substr($lastDot, 1));
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
