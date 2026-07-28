<?php

use YesWiki\Identity\Service\AclService;

// {{mail}} page handler (ticket 18, relocated from tools/contact/handlers/page/mail.php).
// Trimmed to only the "share this page by email" form-rendering branch -- actually
// sending mail now goes through POST /api/contact/mail (ApiController::sendContactMail()),
// which replaces this handler's old ajax branch entirely (see javascripts/contact.js).

$aclService = $this->services->get(AclService::class);
$output = '';

$field = !empty($_GET['field']) ? htmlentities($_GET['field']) : '';
if ($aclService->hasAccess('read') && isset($field) && !empty($field)) {
    $output .= '<form id="ajax-mail-form-handler" class="ajax-mail-form" data-page-tag="' . htmlspecialchars($this->GetPageTag()) . '" data-field="' . $field . '">
        <div class="form-group">
          <div class="input-group">
            <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
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
          <i class="fa fa-envelope"></i>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
        </button>
    </form>';
} elseif ($aclService->hasAccess('read') && $this->GetUser()) {
    // sinon on affiche le formulaire d'envoi de mail
    // si on est identifie
    // on verifie si l'on est bien identifie comme admin, pour eviter le spam
    $output .= '<h1>Envoyer la page par mail</h1>
    <form id="ajax-mail-form-handler" class="ajax-mail-form" data-page-tag="' . htmlspecialchars($this->GetPageTag()) . '">
      <div class="form-group">
        <div class="input-group">
          <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
          <input required class="form-control" type="email" name="email" value=""
                placeholder="' . _t('CONTACT_YOUR_MAIL') . '" />
        </div>
      </div>
      <div class="form-group">
        <div class="input-group">
          <div class="input-group-addon"><i class="fa fa-envelope"></i></div>
          <input required class="form-control" type="email" name="mail"
                    value="" placeholder="' . _t('CONTACT_TO_PLACEHOLDER') . '" />
        </div>
      </div>
      <div class="form-group">
        <input required class="contact-subject form-control" type="text" name="subject"
              value="" placeholder="' . _t('CONTACT_SUBJECT') . '" />
      </div>
      <button class="btn btn-lg btn-block btn-primary mail-submit" type="submit" name="submit">
        <i class="fa fa-envelope"></i>&nbsp;' . _t('CONTACT_SEND_MESSAGE') . '
      </button>
      <input type="hidden" name="type" value="mail" />
    </form>';
} else {
    // on affiche le formulaire d'identification sinon
    $output .= $this->render('@core/alert-message.twig', [
        'type' => 'danger',
        'message' => ($this->GetUser())
            ? _t('LOGIN_NOT_AUTORIZED')
            : (_t('CONTACT_HANDLER_MAIL_FOR_CONNECTED') . '<br />'
                . _t('CONTACT_LOGIN_IF_CONNECTED')),
    ]);
    $output .= $this->Format('{{login}}') . "\n";
}

if ($aclService->hasAccess('read') && ($this->GetUser() || !empty($field))) {
    $this->addJavascriptFile('javascripts/contact.js');
}

// affichage a l'ecran
echo $this->Header();
echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
echo $this->Footer();
