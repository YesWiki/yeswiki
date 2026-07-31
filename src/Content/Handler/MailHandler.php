<?php

namespace YesWiki\Content\Handler;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\AssetRegistry;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Render\Service\TemplateEngine;

/**
 * `/PageName/mail` -- converted from the procedural handlers/page/mail.php by ticket 06.
 */
class MailHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'mail';
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
        // {{mail}} page handler (ticket 18, relocated from tools/contact/handlers/page/mail.php).
        // Trimmed to only the "share this page by email" form-rendering branch -- actually
        // sending mail now goes through POST /api/contact/mail (ApiController::sendContactMail()),
        // which replaces this handler's old ajax branch entirely (see javascripts/contact.js).

        $aclService = $this->getService(AclService::class);
        $output = '';

        $field = !empty($_GET['field']) ? htmlentities($_GET['field']) : '';
        if ($aclService->hasAccess('read') && isset($field) && !empty($field)) {
            $output .= '<form id="ajax-mail-form-handler" class="ajax-mail-form" data-page-tag="' . htmlspecialchars($this->getService(PageContext::class)->getTag()) . '" data-field="' . $field . '">
                <div class="form-group">
                  <div class="input-group">
                    <div class="input-group-addon"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg></div>
                    <input required class="form-control" type="email" name="email" value=""
                        placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
                  </div>
                </div>
                <div class="form-group">
                  <input required class="contact-subject form-control" type="text" name="subject"
                        value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
                </div>
                <div class="form-group">
                  <textarea required rows="6" class="form-control" name="message"
                        placeholder="' . _t('CONTACT_YOUR_MESSAGE') . '"></textarea>
                </div>
                <button class="btn btn-lg btn-block btn-primary mail-submit" type="submit" name="submit">
                  <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
                </button>
            </form>';
        } elseif ($aclService->hasAccess('read') && $this->getService(AuthenticationService::class)->getLoggedUser()) {
            // sinon on affiche le formulaire d'envoi de mail
            // si on est identifie
            // on verifie si l'on est bien identifie comme admin, pour eviter le spam
            $output .= '<h1>Envoyer la page par mail</h1>
            <form id="ajax-mail-form-handler" class="ajax-mail-form" data-page-tag="' . htmlspecialchars($this->getService(PageContext::class)->getTag()) . '">
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-addon"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg></div>
                  <input required class="form-control" type="email" name="email" value=""
                        placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
                </div>
              </div>
              <div class="form-group">
                <div class="input-group">
                  <div class="input-group-addon"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg></div>
                  <input required class="form-control" type="email" name="mail"
                            value="" placeholder="' . _t('CONTACT_TO_PLACEHOLDER') . '" />
                </div>
              </div>
              <div class="form-group">
                <input required class="contact-subject form-control" type="text" name="subject"
                      value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
              </div>
              <button class="btn btn-lg btn-block btn-primary mail-submit" type="submit" name="submit">
                <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#mail"/></svg>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
              </button>
              <input type="hidden" name="type" value="mail" />
            </form>';
        } else {
            // on affiche le formulaire d'identification sinon
            $output .= $this->getService(TemplateEngine::class)->renderSafely('@core/alert-message.twig', [
                'type' => 'danger',
                'message' => ($this->getService(AuthenticationService::class)->getLoggedUser())
                    ? _t('LOGIN_NOT_AUTORIZED')
                    : (_t('CONTACT_HANDLER_MAIL_FOR_CONNECTED') . '<br />'
                        . _t('CONTACT_LOGIN_IF_CONNECTED')),
            ]);
            $output .= $this->getService(MarkdownFormatterService::class)->format('{{login}}') . "\n";
        }

        if ($aclService->hasAccess('read') && ($this->getService(AuthenticationService::class)->getLoggedUser() || !empty($field))) {
            $this->getService(AssetRegistry::class)->addJsFile('javascripts/contact.js');
        }

        // affichage a l'ecran
        echo $this->getService(TemplateEngine::class)->renderPage(
            "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n"
        );
    }
}
