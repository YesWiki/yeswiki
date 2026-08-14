<?php

namespace YesWiki\Core;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\ModuleAclService;
use YesWiki\Kernel\Performable\RegisteredPerformable;
use YesWiki\Kernel\Service\PageContext;
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
            // `$this::` not `static::`: the instanceof narrows $this, and only through $this
            // does the analyser know performableName() exists (ticket 40)
            $actionName = $this::performableName();
        } else {
            // cast because preg_replace() returns null on a failed match, and an ACL key of
            // null is a key nothing matches -- the fallback would silently deny every action
            $actionName = strtolower(get_class($this)); // __greetingaction
            $actionName = (string)preg_replace('/^__|__$/', '', $actionName); // greetingaction
            $actionName = (string)preg_replace('/action$/', '', $actionName); // greeting
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
     * The `@return` this used to carry read `@return false if wrong number of closing element
     * found`, which was meant as prose. `false` is a **type**, so PHPStan narrowed the native
     * `bool` to the literal and concluded that every `if ($this->check_end_elem(...))` in the
     * codebase was dead -- eight of them, all baselined, in grid, col, panel, accordion, label,
     * nav, buttondropdown and section. The branches are not dead; the annotation was wrong, and
     * for as long as it stood a genuine always-false in any of those files was indistinguishable
     * from it (ticket 40).
     *
     * @return bool true when every element of this type has its closing tag
     */
    protected function check_end_elem(string $action_name): bool
    {
        $pagetag = $this->getService(PageContext::class)->getTag();
        if (!isset($GLOBALS["check_$pagetag"][$action_name])) {
            $GLOBALS["check_$pagetag"][$action_name] =
                $this->getService(TemplateHelperService::class)
                    ->checkGraphicalElements($action_name, $pagetag, PageBody::content(($this->getService(PageContext::class)->getPage() ?? [])['body'] ?? []));
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
