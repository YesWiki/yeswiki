<?php

use YesWiki\Core\Service\Performer;
use YesWiki\Core\YesWikiAction;

class EditActionsAclsAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
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
            if (!empty($_GET['actionname']) && $_GET['actionname'] == $action) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($action) . '</option>';
        }
        $res .= '</select> <input type="submit" class="btn btn-default" value="' . _t('SEE') . '" />' . $wiki->FormClose();

        if ($_POST && !empty($_POST['actionname'])) { // save ACL's
            $result = $wiki->SetModuleACL($name = $_POST['actionname'], 'action', @$_POST['acl']);
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            } else {
                $wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_ACTION') . ' ' . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");

                return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION') . ' ' . ucfirst($name) . '.<br />';
            }
        } elseif (!empty($_GET['actionname']) && in_array($name = $_GET['actionname'], $list)) {
            $res .= $wiki->FormOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_ACTION') . ' <strong>' . ucfirst($name) . '</strong>:';
            $res .= '<input type="hidden" name="actionname" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $wiki->GetModuleACL($name, 'action') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $wiki->FormClose();
        }

        return $res;
    }
}
