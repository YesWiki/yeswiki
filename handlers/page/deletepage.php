<?php

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Controller\PageController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;

$csrfTokenManager = $this->services->get(CsrfTokenManager::class);
$csrfTokenController = $this->services->get(CsrfTokenController::class);

if (!empty($_REQUEST['incomingurl'])) {
    $incomingurl = filter_var($_REQUEST['incomingurl'], FILTER_VALIDATE_URL);
}
$redirectToIncoming = false;
$hasBeenDeleted = false;
$confirmed = isset($_GET['confirme']) && $_GET['confirme'] == 'oui';

$deletedByAPreHandler = false;
if ($confirmed && !empty($this->page) && empty($this->services->get(PageManager::class)->getOne($this->GetPageTag(), null, false, true))) {
    try {
        $deletedByAPreHandler = $csrfTokenController->checkToken('main', 'POST', 'csrf-token', false);
    } catch (TokenNotFoundException $th) {
        $deletedByAPreHandler = false;
    }
}

if ($deletedByAPreHandler) {
    $hasBeenDeleted = true;
    $msg = str_replace('{tag}', $this->GetPageTag(), _t('DELETEPAGE_MESSAGE'));
    $redirectToIncoming = !empty($incomingurl);
} elseif ($this->UserIsOwner() || $this->UserIsAdmin()) {
    $incomingUrlParam = '';
    $cancelUrl = $this->Href();
    if (!empty($incomingurl)) {
        $withoutExtraParams = strtok($incomingurl, '&');
        if ($withoutExtraParams != $this->Href()) {
            $incomingUrlParam = '&incomingurl=' . urlencode($incomingurl);
            $cancelUrl = $incomingurl;
        }
    }

    if ($this->IsOrphanedPage($this->GetPageTag())) {
        $tag = $this->GetPageTag();
        if (!$confirmed) {
            $msg = '<form action="' . $this->Href('deletepage', '', 'confirme=oui' . $incomingUrlParam);
            $msg .= '" method="post" style="display: inline">' . "\n";
            $msg .= str_replace('{tag}', $this->Link($tag), _t('DELETEPAGE_CONFIRM')) . "\n";
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
                $csrfTokenController->checkToken('main', 'POST', 'csrf-token', false);
                $hasBeenDeleted = $this->services->get(PageController::class)->delete($tag);
                if ($hasBeenDeleted) {
                    $msg = str_replace('{tag}', $tag, _t('DELETEPAGE_MESSAGE'));
                    $redirectToIncoming = !empty($incomingurl);
                } else {
                    $msg = $this->render('@templates/alert-message-with-back.twig', [
                        'type' => 'danger',
                        'message' => _t('DELETEPAGE_NOT_DELETED'),
                    ]);
                }
            } catch (TokenNotFoundException $th) {
                $msg = $this->render('@templates/alert-message-with-back.twig', [
                    'type' => 'danger',
                    'message' => _t('DELETEPAGE_NOT_DELETED') . ' ' . $th->getMessage(),
                ]);
            }
        }
    } else {
        if (
            isset($_GET['eraselink'])
            && $_GET['eraselink'] === 'oui'
            && $confirmed
        ) {
            try {
                $csrfTokenController->checkToken('main', 'POST', 'csrf-token', false);
            } catch (TokenNotFoundException $th) {
                $msg .= $this->render('@templates/alert-message.twig', [
                    'type' => 'danger',
                    'message' => _t('DELETEPAGE_NOT_DELETED') . ' ' . $th->getMessage(),
                ]);
            }
        }
        $msg = '<p><em>' . _t('DELETEPAGE_NOT_ORPHEANED') . "</em></p>\n";
        $dbService = $this->services->get(DbService::class);
        $linkedFrom = $dbService->loadAll('SELECT DISTINCT from_tag FROM ' . $dbService->prefixTable('links')
            . " WHERE to_tag = '" . $dbService->escape($this->GetPageTag()) . "'");
        $msg .= '<p>' . str_replace('{tag}', $this->ComposeLinkToPage($this->tag, '', '', 0), _t('DELETEPAGE_PAGES_WITH_LINKS_TO')) . "</p>\n";
        $msg .= "<ul>\n";
        foreach ($linkedFrom as $page) {
            $msg .= '<li>' . $this->ComposeLinkToPage($page['from_tag'], '', '', 0) . "</li>\n";
        }

        $msg .= "</ul>\n";
        $msg .= '</br><form action="' . $this->Href('deletepage', '', 'confirme=oui&eraselink=oui' . $incomingUrlParam);
        $msg .= '" method="post" style="display: inline">' . "\n";
        $msg .= str_replace('{tag}', $this->Link($this->tag), _t('DELETEPAGE_CONFIRM_WHEN_BACKLINKS')) . "\n";
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
    $this->SetMessage($msg);
    if ($redirectToIncoming) {
        $tag = $this->GetPageTag();
        $this->Redirect(str_replace(["&action=voir_fiche&id_fiche=$tag", '&message=ajout_ok'], [''], $incomingurl));
    } else {
        $this->Redirect($this->href('', $this->config['root_page']));
    }
}

echo $this->Header();
echo "<div class=\"page\">\n";
echo $msg;
echo "</div>\n";
echo $this->Footer();
