<?php

namespace YesWiki\Render\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\PageContext;

/** `{{pagetitle}}` -- converted from the procedural actions/titrepage.php by ticket 06. */
class PageTitleAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'pagetitle';
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
        $title = htmlspecialchars($this->getService(\YesWiki\Render\Service\TemplateHelperService::class)->getTitleFromBody($this->getService(PageContext::class)->getPage() ?? []), ENT_COMPAT | ENT_HTML5);
        if ($title) {
            echo $title;
        } else {
            echo $this->getService(PageContext::class)->getTag();
        }
    }
}
