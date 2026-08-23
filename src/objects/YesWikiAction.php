<?php

namespace YesWiki\Core;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredPerformable;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\GraphicalElementState;
use YesWiki\Render\Service\TemplateHelperService;

abstract class YesWikiAction extends YesWikiPerformable
{
    /* check if ACL are secured for this action
     * @param  $adminOnly : default to true : only admins can use this action, check action's acl otherwise
     *
     * @return string|null null is all is right otherwise returns the error message
     */
    protected function checkSecuredACL(bool $adminOnly = true): ?string
    {
        if ($this instanceof RegisteredPerformable) {
            $actionName = $this::performableName();
        } else {
            $actionName = strtolower(get_class($this));
            $actionName = (string)preg_replace('/^__|__$/', '', $actionName);
            $actionName = (string)preg_replace('/action$/', '', $actionName);
        }
        $acl = $this->getService(ModuleAclService::class)->getModuleAcl($actionName, 'action');

        if ($adminOnly && in_array($acl, ['*', '+', '', '%']) && !$this->getService(AclService::class)->isAdmin()) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => "Action $actionName : " . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        } elseif (!$this->getService(ModuleAclService::class)->checkModuleAcl($actionName, 'action')) {
            return $this->render('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => "Action $actionName : " . _t('NOT_AUTORIZED') . '.',
            ]);
        }

        return null;
    }

    /**
     * This function check for corresponding "end" element and store result in $GLOBALS['check ' .
     *
     * @return bool true when every element of this type has its closing tag
     */
    protected function check_end_elem(string $action_name): bool
    {
        $pagetag = $this->getService(PageContext::class)->getTag();

        return $this->getService(GraphicalElementState::class)->closesElement(
            $pagetag,
            $action_name,
            fn (): bool => $this->getService(TemplateHelperService::class)
                ->checkGraphicalElements($action_name, $pagetag, PageBody::content(($this->getService(PageContext::class)->getPage() ?? [])['body'] ?? []))
        );
    }

    protected function generate_error_msg(string $action_name): string
    {
        $action_name = strtoupper($action_name);

        return '<div class="yw-alert yw-alert--danger"><strong>'
            . _t("TEMPLATE_ACTION_$action_name") . '</strong> : '
            . _t("TEMPLATE_ELEM_{$action_name}_NOT_CLOSED") . '.</div>' . "\n";
    }

    public function end(): string
    {
        return '\n</div>';
    }
}
