<?php
/**
 * Admin backups
 */
use YesWiki\Core\YesWikiAction;

class AdminBackupsAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'danger',
                'message'=> get_class($this)." : " . _t('BAZ_NEED_ADMIN_RIGHTS')
            ]) ;
        }
        return $this->render('@core/actions/admin-backups.twig', [
        ]);
    }
}
