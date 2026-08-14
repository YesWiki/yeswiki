<?php

namespace YesWiki\Content\Handler;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Service\PageOperationsService;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/PageName/deletepage` -- converted from the procedural handlers/page/deletepage.php by ticket 06.
 *
 * Every deletion is one confirmation. It used to be two screens: a page nothing linked to
 * got a plain confirm, and a page with inbound links got a list of them plus a second,
 * `eraselink=oui` confirm that also cleared those link rows. Ticket 29 removed the `links`
 * table, so there is no set of inbound links to warn about and nothing to erase -- the
 * second screen would have shown an empty list above a button that deleted anyway.
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
        // get services
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

        // get the GET parameter 'incomingurl' for the incoming url
        // declared unconditionally: the redirect at the end of the handler reads it, and it was
        // only assigned when the request carried one (ticket 40)
        $incomingurl = '';
        if (!empty($_REQUEST['incomingurl'])) {
            $incomingurl = filter_var($_REQUEST['incomingurl'], FILTER_VALIDATE_URL);
        }
        $redirectToIncoming = false;
        $hasBeenDeleted = false;

        if ($this->getService(AclService::class)->isOwner() || $this->getService(AclService::class)->isAdmin()) {
            $incomingUrlParam = '';
            $cancelUrl = $this->getService(UrlFormatter::class)->href();
            if (!empty($incomingurl)) {
                $withoutExtraParams = strtok($incomingurl, '&');
                if ($withoutExtraParams != $this->getService(UrlFormatter::class)->href()) {
                    // put the incoming url parameter only if the incoming page is not the one deleted
                    // if the delete page is loaded in a modal box, the incoming page is the modal caller (cf yeswiki-base.js)
                    $incomingUrlParam = '&incomingurl=' . urlencode($incomingurl);
                    $cancelUrl = $incomingurl;
                }
            }

            $tag = $this->getService(PageContext::class)->getTag();
            if (!isset($_GET['confirme']) || !($_GET['confirme'] == 'oui')) {
                $msg = '<form action="' . $this->getService(UrlFormatter::class)->href('deletepage', '', 'confirme=oui' . $incomingUrlParam);
                $msg .= '" method="post" style="display: inline">' . "\n";
                $msg .= str_replace('{tag}', $this->getService(LinkRenderer::class)->link($tag), _t('DELETEPAGE_CONFIRM')) . "\n";
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
                    $hasBeenDeleted = $this->getService(PageOperationsService::class)->delete($tag);
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
                        $msg = $this->getService(TemplateEngine::class)->renderSafely('@core/alert-message-with-back.twig', [
                            'type' => 'danger',
                            'message' => _t('DELETEPAGE_NOT_DELETED'),
                        ]);
                    }
                } catch (TokenNotFoundException $th) {
                    $msg = $this->getService(TemplateEngine::class)->renderSafely('@core/alert-message-with-back.twig', [
                        'type' => 'danger',
                        'message' => _t('DELETEPAGE_NOT_DELETED') . ' ' . $th->getMessage(),
                    ]);
                }
            }
        } else {
            $msg = '<p><em>' . _t('DELETEPAGE_NOT_OWNER') . "</em></p>\n";
        }

        if ($hasBeenDeleted) {
            if ($redirectToIncoming) {
                $this->getService(FlashMessageService::class)->setMessage($msg);
                $this->getService(Redirector::class)->redirect((string)$incomingurl);
            } else {
                // it's the current page which has been deleted (and not from a modal box), redirect to the homepage
                $this->getService(FlashMessageService::class)->setMessage($msg);
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $this->getService(RuntimeConfig::class)['root_page']));
            }
        }

        echo $this->getService(TemplateEngine::class)->renderPage("<div class=\"page\">\n" . $msg . "</div>\n");
    }
}
