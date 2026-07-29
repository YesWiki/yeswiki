<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\PageContext;

/**
 * `/PageName/xml` -- converted from the procedural handlers/page/xml.php by ticket 06.
 */
class XmlHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'xml';
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
        /*
         * handlers/page/xml.php.
         *
         * Permet d'obtenir le contenu d'une page au format xml.
         */
        header('Content-type: text/xml; charset=' . YW_CHARSET);

        if ($HasAccessRead = $this->getService(AclService::class)->hasAccess('read')) {
            // TODO : Return an empty xml ?
            // TODO : Return an error read (noaccess) xml ?
            // TODO : why only serve the body and not all page's properties ?
            // TODO : should exit after echoing ?
            if ($this->getService(PageContext::class)->getPage()) {
                // display page
                echo '<?xml version="1.0" encoding="' . YW_CHARSET . '"?>';
                echo $this->getService(\YesWiki\Render\Service\MarkdownFormatterService::class)->renderActionsOnly($this->getService(PageContext::class)->getPage()['body']);
            }
        }
    }
}
