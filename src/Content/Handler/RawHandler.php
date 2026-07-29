<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;

/**
 * `/PageName/raw` -- converted from the procedural handlers/page/raw.php by ticket 06.
 */
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
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        if ($this->getService(AclService::class)->hasAccess('read')) {
            if (!$this->wiki->page) {
                return;
            }
            header('Content-type: text/plain; charset=' . YW_CHARSET);
            // display raw page
            echo $this->wiki->page['body'];
        } else {
            return;
        }
    }
}
