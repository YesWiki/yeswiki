<?php

namespace YesWiki\Content\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Performable\RegisteredAction;
use YesWiki\Kernel\Service\AssetsManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `{{desabonnement}}` -- converted from the procedural actions/desabonnement.php by ticket 06.
 *
 * The body still prints rather than returning, so it runs inside an output buffer in its
 * own method: that is what the old runFileInBuffer() did, and it keeps any early `return;`
 * in the body from discarding output.
 */
class DesabonnementAction extends YesWikiAction implements RegisteredAction
{
    public static function performableName(): string
    {
        return 'desabonnement';
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
        // ticket 18: relocated from tools/contact/actions/desabonnement.php.
        // action permettant l'envoi par mail d'une demande de desinscription a une liste de discussion

        // recuperation des parametres
        $listelements['mail'] = $this->getService(PerformableArguments::class)->get('mail');
        if (empty($listelements['mail'])) {
            echo '<div class="yw-alert yw-alert--danger"><strong>' . _t('CONTACT_ACTION_DESABONNEMENT') . ' :</strong>&nbsp;' . _t('CONTACT_MAIL_REQUIRED') . '</div>';
        } else {
            // on utilise une variable globale pour savoir de quel formulaire la demande est envoyee, s'il y en a plusieurs sur la meme page
            if (isset($GLOBALS['nbactionmail'])) {
                $GLOBALS['nbactionmail']++;
            } else {
                $GLOBALS['nbactionmail'] = 1;
            }
            $listelements['nbactionmail'] = $GLOBALS['nbactionmail'];

            // on choisit le template utilisé
            $template = $this->getService(PerformableArguments::class)->get('template');
            if (empty($template)) {
                $template = 'subscribe-form.twig';
            }

            $listelements['hiddeninputs'] = '';
            // on indique quel type de liste est utilisé pour formatter les envois de mail de facon adaptee
            $mailinglist = $this->getService(PerformableArguments::class)->get('mailinglist');
            if (!empty($mailinglist) and ($mailinglist == 'ezmlm' or $mailinglist == 'sympa')) {
                $listelements['hiddeninputs'] .= '<input type="hidden" name="mailinglist" value="' . $mailinglist . '">';
            }

            // on peut ajouter des classes à la classe par défaut
            $listelements['class'] = ($this->getService(PerformableArguments::class)->get('class') ? 'form-desabonnement ' . $this->getService(PerformableArguments::class)->get('class') : 'form-desabonnement');

            // page context for the /api/contact/mail route (javascripts/contact.js)
            $listelements['pageTag'] = $this->getService(PageContext::class)->getTag();

            // type de demande et placeholder
            $listelements['demand'] = 'desabonnement';
            $listelements['placeholder'] = _t('CONTACT_UNSUBSCRIBE');

            echo $this->getService(TemplateEngine::class)->renderSafely("@core/$template", $listelements);

            $this->getService(AssetsManager::class)->AddJavascriptFile('javascripts/contact.js');
        }
    }
}
