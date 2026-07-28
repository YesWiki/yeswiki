<?php

namespace YesWiki\Content\Action;

use YesWiki\Kernel\Service\Mailer;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;

/**
 * `{{mailinglist}}` -- converted from the procedural actions/mailinglist.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class MailinglistAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'mailinglist';
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
        // ticket 18: relocated from tools/contact/actions/mailinglist.php.
        // action permettant d'inscrire ou desinscrire massivement des mails a une newsletter


        include_once YESWIKI_SOURCE_DIR . '/src/contact.functions.php';

        // recuperation des parametres
        $list = $this->wiki->GetParameter('list');
        if (empty($list)) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_MAILINGLIST') . '</strong> : ' . _t('CONTACT_PARAMETER_LIST_REQUIRED') . '.</div>';
        } elseif ($this->wiki->UserIsAdmin()) {
            echo '<h2>' . _('CONTACT_MAILS_TO_ADD_OR_REMOVE') . ' ' . $list . '</h2>';

            // les mails formates sont prets a etre envoyes
            if (isset($_POST['mails'])) {
                if (is_array($_POST['mails'])) {
                    $mailer = $this->wiki->services->get(Mailer::class);
                    $tab_listadress = explode('@', $list);

                    // en fonction de l'action demand
                    if ($_POST['action_mails'] == _t('CONTACT_BTN_SUBSCRIBE')) {
                        $listaction = $tab_listadress[0] . '-subscribe@' . $tab_listadress[1];
                    } elseif ($_POST['action_mails'] == _t('CONTACT_BTN_UNSUBSCRIBE')) {
                        $listaction = $tab_listadress[0] . '-unsubscribe@' . $tab_listadress[1];
                    }
                    echo '<div class="well" style="width:600px; height:150px; overflow:auto; ">';
                    foreach ($_POST['mails'] as $email) {
                        echo _t('CONTACT_SENT_TO_THE_LIST') . ' : ' . $listaction . ' ' . _t('CONTACT_THE_EMAIL') . ' : ' . $email;
                        echo $mailer->send($email, $email, $listaction, $_POST['action_mails'], $_POST['action_mails']) ? ' <span class="text-success">' . _t('CONTACT_OK') . '</span>' : '';
                        echo '<br />';
                    }
                    echo '</div>
        			<a href="' . $this->wiki->href() . '" title="' . _t('CONTACT_SUBMIT_OTHER_EMAILS') . '">' . _t('CONTACT_SUBMIT_OTHER_EMAILS') . '</a>';
                }
            }
            // la liste des mails non formatee est disponible
            elseif (isset($_POST['mailinglist'])) {
                // extrait les mails
                $regEx = "/([\s]*)[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i";
                preg_match_all($regEx, $_POST['mailinglist'], $emails);
                if (is_array($emails) && count($emails[0]) > 0) {
                    sort($emails[0]);
                    echo '<form id="ajax-mailing-form" method="post" action="' . $this->wiki->href() . '">
        			<div class="well" style="width:600px; height:150px; overflow:auto; ">';

                    foreach ($emails[0] as $email) {
                        echo $email . '<br /><input name="mails[]" type="hidden" value="' . htmlspecialchars($email, ENT_COMPAT, YW_CHARSET) . '" />';
                        $emails[] = $email;
                    }
                    echo '</div>
        			<strong>' . _t('CONTACT_FOR_ALL_THOSE_EMAILS') . ' : </strong><input class="btn button_save" type="submit" name="action_mails" value="' . _t('CONTACT_BTN_UNSUBSCRIBE') . '" />
        			<input class="btn button_cancel" type="submit" name="action_mails" value="' . _t('CONTACT_BTN_UNSUBSCRIBE') . '" />
        			</form><br /><br />
        			<a href="' . $this->wiki->href() . '" title="' . _t('CONTACT_TRY_WITH_OTHER_EMAILS') . '">' . _t('CONTACT_TRY_WITH_OTHER_EMAILS') . '</a>';
                } else {
                    echo '<div class="yw-alert yw-alert--danger">' . _t('CONTACT_NO_EMAILS_FOUND_IN_THIS_TEXT') . '.</div>
        			<a href="' . $this->wiki->href() . '" title="' . _t('CONTACT_TRY_WITH_OTHER_EMAILS') . '">' . _t('CONTACT_TRY_WITH_OTHER_EMAILS') . '</a>';
                }
            }
            // rien n'a ete fait, on propose un formulaire pour ajouter les mails
            else {
                echo '<div class="yw-alert yw-alert--info">' . _t('CONTACT_ENTER_TEXT_WITH_EMAILS_INSIDE') . '.</div>
        		<form id="ajax-mailing-form" method="post" action="' . $this->wiki->href() . '">
        			<label style="display:inline-block;width:200px;text-align:right;">' . _t('CONTACT_YOUR_EMAIL_LIST') . '</label>
        			<textarea name="mailinglist" rows="6" cols="20" style="width:600px;height:150px;"></textarea>
        			<input class="btn button_save" style="margin:10px 0 10px 205px;" type="submit" name="submit" value="' . _t('CONTACT_EXTRACT_EMAILS_FROM_TEXT') . '" />
        		</form>';
            }
        } else {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_MAILINGLIST') . '</strong> : ' . _t('CONTACT_MUST_BE_ADMIN_TO_USE_THIS_ACTION') . '.</div>' . "\n";
        }
    }
}
