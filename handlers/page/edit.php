<?php

use YesWiki\Core\Service\LinkTracker;
use YesWiki\Core\Service\PageManager;
use YesWiki\Security\Controller\SecurityController;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// on initialise la sortie:
$output = '';

$isWikiHibernated = $this->services->get(SecurityController::class)->isWikiHibernated();

if ($this->HasAccess('write') && $this->HasAccess('read') && !$isWikiHibernated) {
    if (!empty($_POST['submit'])) {
        $submit = $_POST['submit'];
    } else {
        $submit = false;
    }

    // fetch fields
    if (empty($_POST['previous'])) {
        $previous = isset($this->page['id']) ? $this->page['id'] : null;
    } else {
        $previous = $_POST['previous'];
    }
    if (empty($_POST['body'])) {
        $body = isset($this->page['body']) ? $this->page['body'] : null;
    } else {
        $body = $_POST['body'];
    }

    $cancelUrl = addslashes($this->href(testUrlInIframe()));

    // PREVIEW
    if ($submit == 'preview') {
        $temp = $this->SetInclusions(); // a priori, ça ne sert à rien, mais on ne sait jamais...
        $this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
        $output .= $this->render('@core/handlers/edit.twig', [
            'previous' => $previous,
            'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
            'cancelUrl' => $cancelUrl,
            'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
            'preview' => true,
            'bodyPreview' => $this->Format($body),
            'saveValue' => SecurityController::EDIT_PAGE_SUBMIT_VALUE,
        ]);
        $this->SetInclusions($temp);
    } else {
        if ($submit == SecurityController::EDIT_PAGE_SUBMIT_VALUE && $this->page && $this->page['id'] != $_POST['previous']) {
            $error = _t('EDIT_ALERT_ALREADY_SAVED_BY_ANOTHER_USER');
            $submit = false;
        }

        if ($submit == SecurityController::EDIT_PAGE_SUBMIT_VALUE) {
            // SAVE AND REDIRECT
            $body = str_replace("\r", '', $body);
            // teste si la nouvelle page est differente de la précédente
            if (isset($this->page['body']) && rtrim($body) == rtrim($this->page['body'])) {
                $this->SetMessage(_t('EDIT_NO_CHANGE_MSG'));
                $this->Redirect($this->href(testUrlInIframe()));
            } else {
                // l'encodage de la base est en iso-8859-1, voir s'il faut convertir
                $body = _convert($body, YW_CHARSET, true);

                // add page (revisions)
                $this->SavePage($this->tag, $body, !empty($this->page['comment_on']) ? $this->page['comment_on'] : '');

                // now we render it internally so we can write the updated link table.
                $page = $this->services->get(PageManager::class)->getOne($this->tag);
                $this->services->get(LinkTracker::class)->registerLinks($page, false, false);

                // forward
                if ($this->page['comment_on']) {
                    $this->Redirect($this->href(testUrlInIframe(), $this->page['comment_on']) . '#' . $this->tag);
                } else {
                    $this->Redirect($this->href(testUrlInIframe()));
                }
            }
            $this->exit(); // we shall have been redirected, but exit for safety
        } else {
            // RENDER FORM

            // append a comment?
            if (isset($_REQUEST['appendcomment'])) {
                $body = trim($body) . "\n\n----\n\n-- " . $this->GetUserName() . ' (' . date('c') . ')';
            }

            $passwordForEditing = !empty($this->config['password_for_editing']) && isset($_POST['password_for_editing']);

            $output .= $this->render('@core/handlers/edit.twig', [
                'error' => $error ?? null,
                'previous' => $previous,
                'handler' => testUrlInIframe() ? 'editiframe' : 'edit',
                'passwordForEditing' => $passwordForEditing,
                'cancelUrl' => $cancelUrl,
                'body' => empty($body) ? '' : htmlspecialchars($body, ENT_COMPAT, YW_CHARSET),
                'saveValue' => SecurityController::EDIT_PAGE_SUBMIT_VALUE,
                'preview' => false,
            ]);
        }
    }
} else {
    $output .= '<i>' . _t('EDIT_NO_WRITE_ACCESS') . "</i>\n";
    if ($isWikiHibernated) {
        $output .= $this->services->get(SecurityController::class)->getMessageWhenHibernated();
    }
}

// Main Page
$output = '<div class="page">' . "\n" . $output . "\n" . '<hr class="hr_clear" />' . "\n" . '</div>' . "\n";

// Header - // Footer
if (!testUrlInIframe()) {
    echo $this->Header() . $output . $this->Footer();
} else {
    echo $output;
}
