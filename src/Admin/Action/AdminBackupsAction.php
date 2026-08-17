<?php

namespace YesWiki\Admin\Action;

use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;

class AdminBackupsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{adminbackups}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'adminbackups';
    }

    public function components(): array
    {
        return [
            Component::for('adminbackups')
                ->category(Category::Admin)
                ->label(_t('AB_management_adminbackups_label'))
                ->icon('database')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    public function run()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }
        $status = $this->getService(ArchiveService::class)->getArchivingStatus();
        if (!$status['canArchive']) {
            $message = '';

            if ($status['archiving'] === true) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_ARCHIVING');
            } elseif ($status['hibernated'] === true) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_HIBERNATION');
            } elseif ($status['privatePathWritable'] == false) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_WRITABLE_FILE');
            } elseif ($status['canExec'] == false) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_CLI_NOT_WORKING');
            } elseif ($status['notAvailableOnTheInternet'] == false) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_PRIVATE_FOLDER_IS_PUBLIC');
            } elseif ($status['enoughSpace'] == false) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_NO_SPACE');
            } elseif ($status['dB'] == false) {
                $message = _t('ADMIN_BACKUPS_MESSAGE_DB_NOT_ARCHIVABLE');
            }

            return $this->render('@core/alert-message.twig', [
                'type' => 'warning',
                'message' => _t('ADMIN_BACKUPS_MESSAGE_ARCHIVE_CANNOT_BE_DONE') . ' ' . $message . '<br /><a href="?doc#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde">' . _t('ADMIN_BACKUPS_MESSAGE_SEE_DOC') . '</a>.',
            ]);
        }

        return $this->render('@core/actions/admin-backups.twig', [
        ]);
    }
}
