<?php

namespace YesWiki\Identity\Action;

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Performer;

class EditHandlersAclsAction extends YesWikiAction implements RegisteredAction
{
    /** `{{edithandlersacls}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'edithandlersacls';
    }

    public function run()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditHandlersAclsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $wiki = $this->services;
        $list = $wiki->get(Performer::class)->list('handler');
        sort($list);
        $res = $wiki->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen('', '', 'get');
        $res .= _t('HANDLER_RIGHTS') . ' <select name="handlername">';
        foreach ($list as $handler) {
            $res .= '<option value="' . $handler . '"';
            if ($this->getRequest()->query->get('handlername') == $handler) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($handler) . '</option>';
        }
        $res .= '</select> <input class="btn btn-default" type="submit" value="' . _t('SEE') . '" />' . $wiki->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();

        $post = $this->getRequest()->request;
        if ($post->count() > 0 && !empty($post->get('handlername'))) { // save ACL's
            $result = $this->getService(ModuleAclService::class)->setModuleAcl($name = strval($post->get('handlername')), 'handler', strval($post->get('acl')));
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_HANDLER_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            }
            $this->getService(AdministrativeLogService::class)->log($this->getService(AuthenticationService::class)->getLoggedUserName(), _t('NEW_ACL_FOR_HANDLER') . ' ' . ucfirst($name) . ' : ' . $post->get('acl') . "\n");

            return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER') . ' ' . ucfirst($name) . '.<br />';
        } elseif (!empty($this->getRequest()->query->get('handlername')) && in_array($name = $this->getRequest()->query->get('handlername'), $list)) {
            $res .= $wiki->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_HANDLER') . ' <strong>' . ucfirst($name) . '</strong>: <br />';
            $res .= '<input type="hidden" name="handlername" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $this->getService(ModuleAclService::class)->getModuleAcl($name, 'handler') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $wiki->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();
        }

        return $res;
    }
}
