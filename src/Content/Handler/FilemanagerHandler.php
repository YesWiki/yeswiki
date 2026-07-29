<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Attach;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/PageName/filemanager` -- converted from the procedural handlers/page/filemanager.php by ticket 06.
 */
class FilemanagerHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'filemanager';
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
        // {{filemanager}} page handler (ticket 17, relocated from tools/attach/handlers/page/filemanager.php).
        // Manages files linked via the {{attach}} action. Requires actions/attach.php.

        ob_start();
        ?>
        <div class="page">
            <?php
            if ($this->getService(AclService::class)->isOwner() || $this->getService(AclService::class)->isAdmin()) {
                $att = new Attach($this->wiki->services);
                $att->doFileManager();
                unset($att);
            } else {
                echo $this->getService(MarkdownFormatterService::class)->format('//' . _t('FILEMANAGER_ACTION_NEED_ACCESS') . '//');
            }
        ?>
        </div>
        <?php
        $output = ob_get_contents();
        ob_end_clean();
        echo $this->getService(TemplateEngine::class)->header() . $output . $this->getService(TemplateEngine::class)->footer();
    }
}
