<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Files\Service\FileBrowser;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

/** `{{filemanager}}` -- converted from the procedural actions/filemanager.php by ticket 06. */
class FilemanagerAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public static function performableName(): string
    {
        return 'filemanager';
    }

    public function components(): array
    {
        return [
            Component::for('filemanager')
                ->category(Category::Admin)
                ->label(_t('AB_management_filemanager_label'))
                ->icon('folder-open')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
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
        if ($this->getService(AclService::class)->hasAccess('write')) {
            echo $this->getService(FileBrowser::class)->render();
        } else {
            echo '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER') . '.</div>' . "\n";
        }
    }
}
