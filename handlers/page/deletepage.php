<?php

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Controller\PageController;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// get services
$csrfTokenManager = $this->services->get(CsrfTokenManager::class);
$csrfTokenController = $this->services->get(CsrfTokenController::class);

// get the GET parameter 'incomingurl' for the incoming url
if (!empty($_REQUEST['incomingurl'])) {
    $incomingurl = urldecode($_GET['incomingurl']);
}
$redirectToIncoming = false;
$hasBeenDeleted = false;

if ($this->UserIsOwner() || $this->UserIsAdmin()) {
    $incomingUrlParam = '';
    $cancelUrl = $this->Href();
    if (!empty($incomingurl)) {
        $withoutExtraParams = strtok($incomingurl, '&');
        if ($withoutExtraParams != $this->Href()) {
            // put the incoming url parameter only if the incoming page is not the one deleted
            // if the delete page is loaded in a modal box, the incoming page is the modal caller (cf yeswiki-base.js)
            $incomingUrlParam = '&incomingurl=' . urlencode($incomingurl);
            $cancelUrl = $incomingurl;
        }
    }

    if ($this->IsOrphanedPage($this->GetPageTag())) {
        $tag = $this->GetPageTag();
        if (!isset($_GET['confirme']) || !($_GET['confirme'] == 'oui')) {
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
                    // if $incomingurl has been defined and doesn't refer to the deleted page, redirect to it
                    $redirectToIncoming = !empty($incomingurl);
                    if ($redirectToIncoming) {
                        // to prevent errors when deleting entry from BazaR page
                        $incomingurl = str_replace(
                            ["&action=voir_fiche&id_fiche=$tag", '&message=ajout_ok'],
                            [''],
                            $incomingurl
                        );
                    }
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
        if (isset($_GET['eraselink'])
            && $_GET['eraselink'] === 'oui'
            && isset($_GET['confirme'])
            && ($_GET['confirme'] === 'oui')) {
            // a trouble occured, invald token ?
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
        $linkedFrom = $this->LoadAll('SELECT DISTINCT from_tag ' . 'FROM ' . $this->config['table_prefix'] . 'links '
            . "WHERE to_tag = '" . $this->GetPageTag() . "'");
        $msg .= '<p>' . str_replace('{tag}', $this->ComposeLinkToPage($this->tag, '', '', 0), _t('DELETEPAGE_PAGES_WITH_LINKS_TO')) . "</p>\n";
        $msg .= "<ul>\n";
        foreach ($linkedFrom as $page) {
            $msg .= '<li>' . $this->ComposeLinkToPage($page['from_tag'], '', '', 0) . "</li>\n";
        }

        $msg .= "</ul>\n";
        // eraselink=oui will delete the page links in tools/tags/handlers/page/__deletepage.php
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
    if ($redirectToIncoming) {
        $this->SetMessage($msg);
        $this->Redirect($incomingurl);
    } else {
        // it's the current page which has been deleted (and not from a modal box), redirect to the homepage
        $this->SetMessage($msg);
        $this->Redirect($this->href('', $this->config['root_page']));
    }
}

echo $this->Header();
echo "<div class=\"page\">\n";
echo $msg;
echo "</div>\n";
echo $this->Footer();
