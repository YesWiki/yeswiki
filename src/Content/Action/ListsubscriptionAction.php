<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{listsubscription}}` -- converted from the procedural actions/listsubscription.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class ListsubscriptionAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'listsubscription';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // Several of these bodies end in $this->exit(), which throws. The old
            // runFileInBuffer() accumulated output into a by-reference variable, so a throw
            // did not discard what had already been printed; keep that by flushing into the
            // shared output before rethrowing -- and close the buffer either way.
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        // ticket 18: relocated from tools/contact/actions/listsubscription.php.
        // action permettant l'envoi par mail d'une demande d'inscription ou desinscription a une liste
        //
        // Note (found, not fixed): the success branch below builds $output but never echoes
        // it, and the built markup has no submit button or ajax-mail-form/mail-submit wiring
        // anyway -- this looks like pre-existing, already-incomplete/dead functionality, not
        // something this ticket's Mailer/API-route consolidation broke. Left byte-for-byte
        // as before rather than "fixed" into a still-non-functional visible form.

        // valable que pour les utilisateurs connectes
        if ($user = $this->wiki->GetUser()) {
            if ($user['email'] != '') {
                // recuperation des parametres
                $list = $this->wiki->GetParameter('list');
                if (!empty($list)) {
                    $output = '<div class="note"></div>
        				<form id="ajax-abonne-form" class="form-mail" data-page-tag="' . htmlspecialchars($this->wiki->GetPageTag()) . '">
        					' . $list . ' : ' . "\n" .
                        '</form>' . "\n";
                    $this->wiki->addJavascriptFile('javascripts/contact.js');
                } else {
                    echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_LIST_REQUIRED') . '.</div>';
                }
            } else {
                echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NO_EMAIL') . '</div>';
            }
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_LISTSUBSCRIPTION') . '</strong> : ' . _t('CONTACT_USER_NOT_LOGGED_IN') . '</div>';
        }
    }
}
