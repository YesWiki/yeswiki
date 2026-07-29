<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * `/PageName/claim` -- converted from the procedural handlers/page/claim.php by ticket 06.
 */
class ClaimHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'claim';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $tag = $this->getService(PageContext::class)->getTag();
        // only do it on existing pages
        if ($this->getService(PageContext::class)->getPage()) {
            $availableActions = ['opencomments', 'closecomments'];
            // check if actions are requested
            if (
                !empty($_GET['action'])
                && in_array($_GET['action'], $availableActions)
                && ($this->getService(AclService::class)->isAdmin() || $this->getService(AclService::class)->isOwner($tag))
            ) {
                $aclsService = $this->wiki->services->get(AclService::class);
                $commentsAcls = $aclsService->load($tag, 'comment');
                $wikiGroups = $this->getService(GroupOperationsService::class)->getAll();
                switch ($_GET['action']) {
                    case 'opencomments':
                        if (
                            !empty($_GET['list'])
                            && (in_array($_GET['list'], $wikiGroups, true) || $_GET['list'] == '+')
                        ) {
                            $aclsService->save($tag, 'comment', $_GET['list']);
                            $this->getService(FlashMessageService::class)->setMessage(_t('YW_COMMENTS_ARE_NOW_OPEN'));
                        } else {
                            $this->getService(FlashMessageService::class)->setMessage(_t('YW_PROBLEM_WITH_ACLS_LIST'));
                        }
                        break;
                    case 'closecomments':
                        if ($commentsAcls != null) {
                            $aclsService->save($tag, 'comment', 'comments-closed');
                            $this->getService(FlashMessageService::class)->setMessage(_t('YW_COMMENTS_ARE_NOW_CLOSED'));
                        } else {
                            $this->getService(FlashMessageService::class)->setMessage(_t('YW_COMMENTS_ALREADY_CLOSED'));
                        }
                        break;
                }
            }

            // only claim ownership if this page has no owner, and if user is logged in.
            if (!$this->getService(PageManager::class)->getOwner() && $this->getService(AuthenticationService::class)->getLoggedUser()) {
                $this->getService(PageManager::class)->setOwner($tag, $this->getService(AuthenticationService::class)->getLoggedUserName());
                $this->getService(FlashMessageService::class)->setMessage(_t('YW_YOU_ARE_NOW_OWNER_OF_PAGE'));
            }
        }

        $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
    }
}
