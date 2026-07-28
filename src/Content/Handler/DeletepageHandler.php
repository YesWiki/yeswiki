<?php

namespace YesWiki\Content\Handler;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Kernel\Performable\RegisteredHandler;

/**
 * `/PageName/deletepage` -- converted from the procedural handlers/page/deletepage.php by ticket 06.
 */
class DeletepageHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'deletepage';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emitBefore();
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    /**
     * Ran as a before-callback until ticket 06 merged it in.
     */
    private function emitBefore(): void
    {
        // merged from handlers/page/__deletepage.php (ticket 06: core does not hook itself)
        if (($this->wiki->UserIsOwner() || $this->wiki->UserIsAdmin())
            && isset($_GET['eraselink'])
            && $_GET['eraselink'] === 'oui'
            && isset($_GET['confirme'])
            && ($_GET['confirme'] === 'oui')
        ) {
            try {
                if ($this->wiki->services->get(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false)) {
                    $tag = $this->wiki->GetPageTag();
                    $dbService = $this->wiki->services->get(DbService::class);
                    $dbService->query("DELETE FROM {$dbService->prefixTable('links')} WHERE to_tag = '" . $dbService->escape($tag) . "'");
                }
            } catch (Throwable $th) {
                // do nothing
            }
        }
    }

    private function emit(): void
    {

        // get services
        $csrfTokenManager = $this->wiki->services->get(CsrfTokenManager::class);
        $csrfTokenChecker = $this->wiki->services->get(CsrfTokenChecker::class);

        // get the GET parameter 'incomingurl' for the incoming url
        if (!empty($_REQUEST['incomingurl'])) {
            $incomingurl = filter_var($_REQUEST['incomingurl'], FILTER_VALIDATE_URL);
        }
        $redirectToIncoming = false;
        $hasBeenDeleted = false;

        if ($this->wiki->UserIsOwner() || $this->wiki->UserIsAdmin()) {
            $incomingUrlParam = '';
            $cancelUrl = $this->wiki->Href();
            if (!empty($incomingurl)) {
                $withoutExtraParams = strtok($incomingurl, '&');
                if ($withoutExtraParams != $this->wiki->Href()) {
                    // put the incoming url parameter only if the incoming page is not the one deleted
                    // if the delete page is loaded in a modal box, the incoming page is the modal caller (cf yeswiki-base.js)
                    $incomingUrlParam = '&incomingurl=' . urlencode($incomingurl);
                    $cancelUrl = $incomingurl;
                }
            }

            if ($this->wiki->IsOrphanedPage($this->wiki->GetPageTag())) {
                $tag = $this->wiki->GetPageTag();
                if (!isset($_GET['confirme']) || !($_GET['confirme'] == 'oui')) {
                    $msg = '<form action="' . $this->wiki->Href('deletepage', '', 'confirme=oui' . $incomingUrlParam);
                    $msg .= '" method="post" style="display: inline">' . "\n";
                    $msg .= str_replace('{tag}', $this->wiki->Link($tag), _t('DELETEPAGE_CONFIRM')) . "\n";
                    $msg .= '</br></br>';
                    $msg .= '<input type="hidden" name="csrf-token" value="' . htmlentities($csrfTokenManager->getToken('main')) . '">';
                    $msg .= '<input type="submit" class="btn btn-danger" value="' . _t('DELETEPAGE_DELETE') . '" ';
                    $msg .= 'style="vertical-align: middle; display: inline" />' . "\n";
                    $msg .= "</form>\n";
                    $msg .= '<form action="' . $cancelUrl . '" method="post" style="display: inline">' . "\n";
                    $msg .= '<input type="submit" value="' . _t('DELETEPAGE_CANCEL') . '" class="btn btn-default" style="vertical-align: middle; display: inline" />' . "\n";
                    $msg .= "</form></span>\n";
                } else {
                    try {
                        $csrfTokenChecker->checkToken('main', 'POST', 'csrf-token', false);
                        $hasBeenDeleted = $this->wiki->services->get(PageOperationsService::class)->delete($tag);
                        if ($hasBeenDeleted) {
                            $msg = str_replace('{tag}', $tag, _t('DELETEPAGE_MESSAGE'));
                            // if $incomingurl has been defined and doesn't refer to the deleted page, redirect to it
                            $redirectToIncoming = !empty($incomingurl);
                            if ($redirectToIncoming) {
                                // to prevent errors when deleting entry from BazaR page
                                $incomingurl = str_replace(
                                    ["&action=voir_fiche&tag=$tag", '&message=ajout_ok'],
                                    [''],
                                    $incomingurl
                                );
                            }
                        } else {
                            $msg = $this->wiki->render('@core/alert-message-with-back.twig', [
                                'type' => 'danger',
                                'message' => _t('DELETEPAGE_NOT_DELETED'),
                            ]);
                        }
                    } catch (TokenNotFoundException $th) {
                        $msg = $this->wiki->render('@core/alert-message-with-back.twig', [
                            'type' => 'danger',
                            'message' => _t('DELETEPAGE_NOT_DELETED') . ' ' . $th->getMessage(),
                        ]);
                    }
                }
            } else {
                if (
                    isset($_GET['eraselink'])
                    && $_GET['eraselink'] === 'oui'
                    && isset($_GET['confirme'])
                    && ($_GET['confirme'] === 'oui')
                ) {
                    // a trouble occured, invald token ?
                    try {
                        $csrfTokenChecker->checkToken('main', 'POST', 'csrf-token', false);
                    } catch (TokenNotFoundException $th) {
                        $msg .= $this->wiki->render('@core/alert-message.twig', [
                            'type' => 'danger',
                            'message' => _t('DELETEPAGE_NOT_DELETED') . ' ' . $th->getMessage(),
                        ]);
                    }
                }
                $msg = '<p><em>' . _t('DELETEPAGE_NOT_ORPHEANED') . "</em></p>\n";
                $dbService = $this->wiki->services->get(DbService::class);
                $linkedFrom = $dbService->loadAll('SELECT DISTINCT from_tag FROM ' . $dbService->prefixTable('links')
                    . " WHERE to_tag = '" . $dbService->escape($this->wiki->GetPageTag()) . "'");
                $msg .= '<p>' . str_replace('{tag}', $this->wiki->ComposeLinkToPage($this->wiki->tag, '', '', 0), _t('DELETEPAGE_PAGES_WITH_LINKS_TO')) . "</p>\n";
                $msg .= "<ul>\n";
                foreach ($linkedFrom as $page) {
                    $msg .= '<li>' . $this->wiki->ComposeLinkToPage($page['from_tag'], '', '', 0) . "</li>\n";
                }

                $msg .= "</ul>\n";
                // eraselink=oui will delete the page links in handlers/page/__deletepage.php
                $msg .= '</br><form action="' . $this->wiki->Href('deletepage', '', 'confirme=oui&eraselink=oui' . $incomingUrlParam);
                $msg .= '" method="post" style="display: inline">' . "\n";
                $msg .= str_replace('{tag}', $this->wiki->Link($this->wiki->tag), _t('DELETEPAGE_CONFIRM_WHEN_BACKLINKS')) . "\n";
                $msg .= '</br></br>';
                $msg .= '<input type="hidden" name="csrf-token" value="' . htmlentities($csrfTokenManager->getToken('main')) . '">';
                $msg .= '<input type="submit" value="' . _t('DELETEPAGE_DELETE') . '" class="btn btn-danger" ';
                $msg .= 'style="vertical-align: middle; display: inline" />' . "\n";
                $msg .= "</form>\n";
                $msg .= '<form action="' . $cancelUrl . '" method="post" style="display: inline">' . "\n";
                $msg .= '<input type="submit" value="' . _t('DELETEPAGE_CANCEL') . '" class="btn btn-default" style="vertical-align: middle; display: inline" />' . "\n";
                $msg .= "</form></span>\n";
            }
        } else {
            $msg = '<p><em>' . _t('DELETEPAGE_NOT_OWNER') . "</em></p>\n";
        }

        if ($hasBeenDeleted) {
            if ($redirectToIncoming) {
                $this->wiki->SetMessage($msg);
                $this->wiki->Redirect($incomingurl);
            } else {
                // it's the current page which has been deleted (and not from a modal box), redirect to the homepage
                $this->wiki->SetMessage($msg);
                $this->wiki->Redirect($this->wiki->href('', $this->wiki->config['root_page']));
            }
        }

        echo $this->wiki->Header();
        echo "<div class=\"page\">\n";
        echo $msg;
        echo "</div>\n";
        echo $this->wiki->Footer();
    }
}
