<?php

namespace YesWiki\Core;

abstract class YesWikiAction extends YesWikiPerformable
{
    /* check if ACL are secured for this action
     * @return string|null null is all is right otherwise returns the error message
     */
    protected function checkSecuredACL(): ?string
    {
        $actionName = strtolower(get_class($this)); // __greetingaction
        $actionName = preg_replace('/^__|__$/', '', $actionName); // greetingaction
        $actionName = preg_replace('/action$/', '', $actionName); // greeting
        // check access (only admins or follow acl if defined)
        $acl = $this->wiki->GetModuleACL($actionName, 'action');
        if (in_array($acl, ['*', '+', '', '%']) && !$this->wiki->UserIsAdmin()) {
            // the acl is defined with not secured values or not defined, and user is not admin
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => "Action $actionName : " . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        } else {
            return null;
        }
    }
}
