<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\EntryManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Render\Service\MarkdownFormatterService;

/**
 * `/PageName/html` -- converted from the procedural handlers/page/html.php by ticket 06.
 */
class HtmlHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'html';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emitBefore();
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    /**
     * Ran as a before-callback until ticket 06 merged it in.
     */
    private function emitBefore(): void
    {
        // merged from handlers/page/__html.php (ticket 06: core does not hook itself)
        $entryManager = $this->wiki->services->get(EntryManager::class);

        if ($entryManager->isEntry($this->wiki->GetPageTag())) {
            $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/bazar.js', true, true);
            $fiche = $entryManager->getOne($this->wiki->GetPageTag());
            $this->wiki->page['body'] = '""' . baz_voir_fiche(0, $fiche) . '""';
        }
    }

    private function emit(): void
    {
        // Verification de securite

        if ($this->getService(AclService::class)->hasAccess('read')) {
            if (!$this->wiki->page) {
                return;
            }
            // affichage de la page formatee
            echo "<div class=\"page\">\n" . $this->getService(MarkdownFormatterService::class)->format($this->wiki->page['body']) . "\n</div>\n";
        } else {
            return;
        }
    }
}
