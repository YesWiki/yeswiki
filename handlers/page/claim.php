<?php

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

use YesWiki\Core\Service\AclService;

$tag = $this->getPageTag();
// only do it on existing pages
if ($this->page) {
    $availableActions = ['opencomments', 'closecomments'];
    // check if actions are requested
    if (
        !empty($_GET['action'])
        && in_array($_GET['action'], $availableActions)
        && ($this->UserIsAdmin() || $this->UserIsOwner($tag))
    ) {
        $aclsService = $this->services->get(AclService::class);
        $commentsAcls = $aclsService->load($tag, 'comment');
        $wikiGroups = $this->GetGroupsList();
        switch ($_GET['action']) {
            case 'opencomments':
                if (
                    !empty($_GET['list'])
                    && (in_array($_GET['list'], $wikiGroups, true) || $_GET['list'] == '+')
                ) {
                    $aclsService->save($tag, 'comment', $_GET['list']);
                    $this->SetMessage(_t('YW_COMMENTS_ARE_NOW_OPEN'));
                } else {
                    $this->SetMessage(_t('YW_PROBLEM_WITH_ACLS_LIST'));
                }
                break;
            case 'closecomments':
                if ($commentsAcls != null) {
                    $aclsService->save($tag, 'comment', 'comments-closed');
                    $this->SetMessage(_t('YW_COMMENTS_ARE_NOW_CLOSED'));
                } else {
                    $this->SetMessage(_t('YW_COMMENTS_ALREADY_CLOSED'));
                }
                break;
        }
    }

    // only claim ownership if this page has no owner, and if user is logged in.
    if (!$this->GetPageOwner() && $this->GetUser()) {
        $this->SetPageOwner($tag, $this->GetUserName());
        $this->SetMessage(_t('YW_YOU_ARE_NOW_OWNER_OF_PAGE'));
    }
}

$this->Redirect($this->href());
