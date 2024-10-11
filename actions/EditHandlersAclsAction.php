<?php

use YesWiki\Core\Service\Performer;
use YesWiki\Core\YesWikiAction;

class EditHandlersAclsAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditHandlersAclsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $wiki = &$this->wiki;
        $list = $wiki->services->get(Performer::class)->list('handler');
        sort($list);
        $res = $wiki->FormOpen('', '', 'get');
        $res .= _t('HANDLER_RIGHTS') . ' <select name="handlername">';
        foreach ($list as $handler) {
            $res .= '<option value="' . $handler . '"';
            if (!empty($_GET['handlername']) && $_GET['handlername'] == $handler) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($handler) . '</option>';
        }
        $res .= '</select> <input class="btn btn-default" type="submit" value="' . _t('SEE') . '" />' . $wiki->FormClose();

        if ($_POST && !empty($_POST['handlername'])) { // save ACL's
            $result = $wiki->SetModuleACL($name = $_POST['handlername'], 'handler', @$_POST['acl']);
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_HANDLER_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            } else {
                $wiki->LogAdministrativeAction($wiki->GetUserName(), _t('NEW_ACL_FOR_HANDLER') . ' ' . ucfirst($name) . ' : ' . @$_POST['acl'] . "\n");

                return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER') . ' ' . ucfirst($name) . '.<br />';
            }
        } elseif (!empty($_GET['handlername']) && in_array($name = $_GET['handlername'], $list)) {
            $res .= $wiki->FormOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_HANDLER') . ' <strong>' . ucfirst($name) . '</strong>: <br />';
            $res .= '<input type="hidden" name="handlername" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $wiki->GetModuleACL($name, 'handler') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $wiki->FormClose();
        }

        return $res;
    }
}
