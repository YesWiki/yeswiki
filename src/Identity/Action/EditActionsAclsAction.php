<?php

namespace YesWiki\Identity\Action;

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Performer;

class EditActionsAclsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{editactionsacls}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'editactionsacls';
    }

    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditActionsAclsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $wiki = &$this->wiki;
        $list = $wiki->services->get(Performer::class)->list('action');
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= _t('ACTION_RIGHTS') . ' <select name="actionname">';
        foreach ($list as $action) {
            $res .= '<option value="' . $action . '"';
            if ($this->getRequest()->query->get('actionname') == $action) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($action) . '</option>';
        }
        $res .= '</select> <input type="submit" class="btn btn-default" value="' . _t('SEE') . '" />' . $wiki->FormClose();

        $post = $this->getRequest()->request;
        if ($post->count() > 0 && !empty($post->get('actionname'))) { // save ACL's
            $result = $this->getService(ModuleAclService::class)->setModuleAcl($name = strval($post->get('actionname')), 'action', strval($post->get('acl')));
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            }
            $this->getService(AdministrativeLogService::class)->log($wiki->GetUserName(), _t('NEW_ACL_FOR_ACTION') . ' ' . ucfirst($name) . ' : ' . $post->get('acl') . "\n");

            return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION') . ' ' . ucfirst($name) . '.<br />';
        } elseif (!empty($this->getRequest()->query->get('actionname')) && in_array($name = $this->getRequest()->query->get('actionname'), $list)) {
            $res .= $wiki->FormOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_ACTION') . ' <strong>' . ucfirst($name) . '</strong>:';
            $res .= '<input type="hidden" name="actionname" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $this->getService(ModuleAclService::class)->getModuleAcl($name, 'action') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $wiki->FormClose();
        }

        return $res;
    }
}
