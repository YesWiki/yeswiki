<?php

namespace YesWiki\Content\Handler;

use YesWiki\Identity\Service\AclService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;

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

        $tag = $this->wiki->getPageTag();
        // only do it on existing pages
        if ($this->wiki->page) {
            $availableActions = ['opencomments', 'closecomments'];
            // check if actions are requested
            if (
                !empty($_GET['action'])
                && in_array($_GET['action'], $availableActions)
                && ($this->wiki->UserIsAdmin() || $this->wiki->UserIsOwner($tag))
            ) {
                $aclsService = $this->wiki->services->get(AclService::class);
                $commentsAcls = $aclsService->load($tag, 'comment');
                $wikiGroups = $this->wiki->GetGroupsList();
                switch ($_GET['action']) {
                    case 'opencomments':
                        if (
                            !empty($_GET['list'])
                            && (in_array($_GET['list'], $wikiGroups, true) || $_GET['list'] == '+')
                        ) {
                            $aclsService->save($tag, 'comment', $_GET['list']);
                            $this->wiki->SetMessage(_t('YW_COMMENTS_ARE_NOW_OPEN'));
                        } else {
                            $this->wiki->SetMessage(_t('YW_PROBLEM_WITH_ACLS_LIST'));
                        }
                        break;
                    case 'closecomments':
                        if ($commentsAcls != null) {
                            $aclsService->save($tag, 'comment', 'comments-closed');
                            $this->wiki->SetMessage(_t('YW_COMMENTS_ARE_NOW_CLOSED'));
                        } else {
                            $this->wiki->SetMessage(_t('YW_COMMENTS_ALREADY_CLOSED'));
                        }
                        break;
                }
            }

            // only claim ownership if this page has no owner, and if user is logged in.
            if (!$this->wiki->GetPageOwner() && $this->wiki->GetUser()) {
                $this->wiki->SetPageOwner($tag, $this->wiki->GetUserName());
                $this->wiki->SetMessage(_t('YW_YOU_ARE_NOW_OWNER_OF_PAGE'));
            }
        }

        $this->wiki->Redirect($this->wiki->href());
    }
}
