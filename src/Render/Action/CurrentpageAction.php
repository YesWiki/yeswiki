<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

/** `{{currentpage}}` -- converted from the procedural actions/currentpage.php by ticket 06. */
class CurrentpageAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'currentpage';
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
        echo $this->getService(PageContext::class)->getTag();
    }
}
