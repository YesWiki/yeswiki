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
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        $csrfTokenManager = $this->getService(CsrfTokenManager::class);
        $csrfTokenChecker = $this->getService(CsrfTokenChecker::class);

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

                        $redirectToIncoming = !empty($incomingurl);
                        if ($redirectToIncoming) {
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
                $this->getService(FlashMessageService::class)->setMessage($msg);
                $this->getService(Redirector::class)->redirect($this->getService(UrlFormatter::class)->href('', $this->getService(RuntimeConfig::class)['root_page']));
            }
        }

        echo $this->getService(TemplateEngine::class)->renderPage($msg . "\n");
    }
}
