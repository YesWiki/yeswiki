<?php

namespace YesWiki\Core;

abstract class YesWikiAction extends YesWikiPerformable
{
    /* check if ACL are secured for this action
     * @param  $adminOnly : default to true : only admins can use this action, check action's acl otherwise
     *
     * @return string|null null is all is right otherwise returns the error message
     */
    protected function checkSecuredACL($adminOnly = true): ?string
    {
        $actionName = strtolower(get_class($this)); // __greetingaction
        $actionName = preg_replace('/^__|__$/', '', $actionName); // greetingaction
        $actionName = preg_replace('/action$/', '', $actionName); // greeting
        // check access (only admins or follow acl if defined)
        $acl = $this->wiki->GetModuleACL($actionName, 'action');

        // For admin actions, if the acl is defined with not secured values or not defined
        if ($adminOnly && in_array($acl, ['*', '+', '', '%']) && !$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => "Action $actionName : " . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        } elseif (!$this->wiki->CheckModuleACL($actionName, 'action')) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => "Action $actionName : " . _t('NOT_AUTORIZED') . '.',
            ]);
        } else {
            return null;
        }
    }

    public function end(): string
    {
        return '\n</div>';
    }
}
