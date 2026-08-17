<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;

/** `/PageName/raw` -- converted from the procedural handlers/page/raw.php by ticket 06. */
class RawHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'raw';
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
        if ($this->getService(AclService::class)->hasAccess('read')) {
            if (!$this->getService(PageContext::class)->getPage()) {
                return;
            }
            header('Content-type: text/plain; charset=' . YW_CHARSET);

            echo PageBody::content($this->getService(PageContext::class)->getPage()['body']);
        } else {
            return;
        }
    }
}
