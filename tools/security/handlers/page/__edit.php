<?php

use YesWiki\Core\Service\BotGuard;
use YesWiki\Security\Controller\SecurityController;

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    $securityController = $this->services->get(SecurityController::class);
    list($state, $message) = $securityController->isGrantedPasswordForEditing();
    if (!$state) {
        echo $this->Header() .
            $message .
            $this->Footer();
        $this->exit();
    }

    if (($_POST['submit'] ?? null) == SecurityController::EDIT_PAGE_SUBMIT_VALUE) {
        $botGuard = $this->services->get(BotGuard::class);
        $refusal = $botGuard->check($this->request);
        if ($refusal !== null) {
            $error = $botGuard->message($refusal);
            $_POST['submit'] = '';
        }
    }

    if ($this->config['use_alerte']) {
        $js = 'var showPopup = 0;

        $(\'#body\').on(\'input selectionchange propertychange\', function() {
          showPopup = 1;
        });

        $(\'#ACEditor, .bazar-form\').on(\'submit\', function() {
          showPopup = 0;
        });

        $(window).on(\'beforeunload\', function(e) {
          if (showPopup) {
            return true;
          }
        });';

        $this->AddJavascript($js);
    }
}
