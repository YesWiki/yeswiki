<?php

namespace YesWiki\Core;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredPerformable;
use YesWiki\Render\Service\TemplateHelperService;

abstract class YesWikiAction extends YesWikiPerformable
{
    /* check if ACL are secured for this action
     * @param  $adminOnly : default to true : only admins can use this action, check action's acl otherwise
     *
     * @return string|null null is all is right otherwise returns the error message
     */
    protected function checkSecuredACL($adminOnly = true): ?string
    {
        // A registered action states its name; deriving it from get_class() only worked
        // while these classes had no namespace, because the FQCN would otherwise leak into
        // the ACL key (ticket 06). The derivation stays as the fallback for actions still
        // resolved by the directory scan.
        if ($this instanceof RegisteredPerformable) {
            $actionName = static::performableName();
        } else {
            $actionName = strtolower(get_class($this)); // __greetingaction
            $actionName = preg_replace('/^__|__$/', '', $actionName); // greetingaction
            $actionName = preg_replace('/action$/', '', $actionName); // greeting
        }
        // check access (only admins or follow acl if defined)
        $acl = $this->getService(ModuleAclService::class)->getModuleAcl($actionName, 'action');

        // For admin actions, if the acl is defined with not secured values or not defined
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
     * This function check for corresponding "end" element and store result in
     * $GLOBALS['check ' . $pagetag]['$element_name'].
     *
     * @return false if wrong number of closing element found
     */
    protected function check_end_elem(string $action_name): bool
    {
        $pagetag = $this->wiki->GetPageTag();
        if (!isset($GLOBALS["check_$pagetag"][$action_name])) {
            $GLOBALS["check_$pagetag"][$action_name] =
                $this->wiki->services->get(TemplateHelperService::class)
                    ->checkGraphicalElements($action_name, $pagetag, $this->wiki->page['body'] ?? '');
        }

        return $GLOBALS["check_$pagetag"][$action_name];
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
