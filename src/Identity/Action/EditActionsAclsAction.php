<?php

namespace YesWiki\Identity\Action;

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Performer;

class EditActionsAclsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{editactionsacls}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'editactionsacls';
    }

    public function components(): array
    {
        return [
            Component::for('editactionsacls')
                ->category(Category::Admin)
                ->label(_t('AB_management_editactionsacls_label'))
                ->icon('lock')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    public function run()
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditActionsAclsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $services = $this->services;
        $list = $services->get(Performer::class)->list('action');
        sort($list);
        $res = $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen('', '', 'get');
        $res .= _t('ACTION_RIGHTS') . ' <select name="actionname">';
        foreach ($list as $action) {
            $res .= '<option value="' . $action . '"';
            if ($this->getRequest()->query->get('actionname') == $action) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($action) . '</option>';
        }
        $res .= '</select> <input type="submit" class="btn btn-default" value="' . _t('SEE') . '" />' . $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();

        $post = $this->getRequest()->request;
        if ($post->count() > 0 && !empty($post->get('actionname'))) {
            $result = $this->getService(ModuleAclService::class)->setModuleAcl($name = strval($post->get('actionname')), 'action', strval($post->get('acl')));
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            }
            $this->getService(AdministrativeLogService::class)->log($this->getService(AuthenticationService::class)->getLoggedUserName(), _t('NEW_ACL_FOR_ACTION') . ' ' . ucfirst($name) . ' : ' . $post->get('acl') . "\n");

            return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_ACTION') . ' ' . ucfirst($name) . '.<br />';
        } elseif (!empty($this->getRequest()->query->get('actionname')) && in_array($name = $this->getRequest()->query->get('actionname'), $list)) {
            $res .= $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_ACTION') . ' <strong>' . ucfirst($name) . '</strong>:';
            $res .= '<input type="hidden" name="actionname" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $this->getService(ModuleAclService::class)->getModuleAcl($name, 'action') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();
        }

        return $res;
    }
}
