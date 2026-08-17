<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\RuntimeConfig;

/** `{{yeswikiversion}}` -- converted from the procedural actions/yeswikiversion.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        echo '<div class="yw-text-center">(>^_^)> ' . _t('RUNNING_WITH') . ' <a title="' . $this->getService(RuntimeConfig::class)['yeswiki_version'] . ' ' . $this->getService(RuntimeConfig::class)['yeswiki_release'] . '" href="https://www.yeswiki.net">YesWiki</a> <(^_^<)</div>' . "\n";
    }
}
