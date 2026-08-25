<?php

namespace YesWiki\Identity\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Render\Service\Performer;

class EditHandlersAclsAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{edithandlersacls}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'edithandlersacls';
    }

    public function components(): array
    {
        return [
            Component::for('edithandlersacls')
                ->category(Category::Admin)
                ->label(_t('AB_management_edithandlersacls_label'))
                ->icon('lock')
                ->previewHeight('200px')
                ->adminOnly(),
        ];
    }

    public function run(): string
    {
        if (!$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditHandlersAclsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $services = $this->services;
        $list = $services->get(Performer::class)->list('handler');
        sort($list);
        $res = $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen('', '', 'get');
        $res .= _t('HANDLER_RIGHTS') . ' <select name="handlername">';
        foreach ($list as $handler) {
            $res .= '<option value="' . $handler . '"';
            if ($this->getRequest()->query->get('handlername') == $handler) {
                $res .= ' selected="selected"';
            }
            $res .= '>' . ucfirst($handler) . '</option>';
        }
        $res .= '</select> <input class="btn btn-default" type="submit" value="' . _t('SEE') . '" />' . $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();

        $post = $this->getRequest()->request;
        if ($post->count() > 0 && !empty($post->get('handlername'))) {
            $result = $this->getService(ModuleAclService::class)->setModuleAcl($name = strval($post->get('handlername')), 'handler', strval($post->get('acl')));
            if ($result) {
                return $res . _t('ERROR_WHILE_SAVING_HANDLER_ACL') . ' ' . ucfirst($name) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')<br />';
            }
            $this->getService(Journal::class)->audit('acl.change', $name, ['scope' => 'handler', 'acl' => strval($post->get('acl'))]);

            return $res . _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_HANDLER') . ' ' . ucfirst($name) . '.<br />';
        } elseif (!empty($this->getRequest()->query->get('handlername')) && in_array($name = $this->getRequest()->query->get('handlername'), $list)) {
            $res .= $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formOpen();
            $res .= '<br />' . _t('EDIT_RIGHTS_FOR_HANDLER') . ' <strong>' . ucfirst($name) . '</strong>: <br />';
            $res .= '<input type="hidden" name="handlername" value="' . $name . '" />';
            $res .= '<textarea class="form-control" name="acl" rows="3">' . $this->getService(ModuleAclService::class)->getModuleAcl($name, 'handler') . '</textarea><br />';
            $res .= '<input type="submit" value="' . _t('SAVE') . '" class="btn btn-primary" accesskey="s" />';

            return $res . $services->get(\YesWiki\Render\Service\TemplateEngine::class)->formClose();
        }

        return $res;
    }
}
