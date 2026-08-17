<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{lang}}` -- converted from the procedural actions/lang.php by ticket 06. */
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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
    }
}
