<?php
/**
 * Admin backups.
 */
use YesWiki\Core\Service\ArchiveService;
use YesWiki\Core\YesWikiAction;

class AdminBackupsAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => get_class($this) . ' : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }
        $status = $this->getService(ArchiveService::class)->getArchivingStatus();
        if (!$status['canArchive']) {
            $message = '';

            if ($status['hibernated'] === true) {
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

            return $this->render('@templates/alert-message.twig', [
                'type' => 'warning',
                'message' => _t('ADMIN_BACKUPS_MESSAGE_ARCHIVE_CANNOT_BE_DONE') . ' ' . $message . '<br /><a href="?doc#/docs/fr/admin?id=résoudre-les-problèmes-de-sauvegarde">' . _t('ADMIN_BACKUPS_MESSAGE_SEE_DOC') . '</a>.',
            ]);
        }

        return $this->render('@core/actions/admin-backups.twig', [
        ]);
    }
}
