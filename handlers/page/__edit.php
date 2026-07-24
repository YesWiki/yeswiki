<?php

use YesWiki\Core\Controller\CaptchaController;
use YesWiki\Core\Controller\SecurityController;
use YesWiki\Core\Service\HashCashService;
use YesWiki\Core\Service\PasswordForEditingService;

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    list($state, $message) = $this->services->get(PasswordForEditingService::class)->isGrantedPasswordForEditing();
    if (!$state) {
        echo $this->Header() .
            $message .
            $this->Footer();
        $this->exit();
    }

    if (
        $this->config['use_hashcash']
        && isset($_POST['submit']) && $_POST['submit'] == SecurityController::EDIT_PAGE_SUBMIT_VALUE
        && !$this->services->get(HashCashService::class)->checkHashcash()
    ) {
        $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>' . _t('HASHCASH_ERROR_PAGE_UNSAVED') . '</div>';
        $_POST['submit'] = '';
    }

    list($state, $error) = $this->services->get(CaptchaController::class)->checkCaptchaBeforeSave();

    if ($state) {
        // error used in edit.php
        unset($error);
    }

    if ($this->config['use_alerte']) {
        $js = "// par défaut, pas de popup d'alerte pour quitter la page
        var showPopup = false;

        // on demande a faire apparaitre la popup si la page a été modifiée
        var bodyField = document.getElementById('body');
        if (bodyField) {
            bodyField.addEventListener('input', function() {
                showPopup = true;
            });
        }

        // on annule la popup si l'on sauve la page
        ['ACEditor', 'formulaire'].forEach(function(id) {
            var formEl = document.getElementById(id);
            if (formEl) {
                formEl.addEventListener('submit', function() {
                    showPopup = false;
                });
            }
        });

        // si l'on quitte la page, on affiche la popup si besoin
        window.addEventListener('beforeunload', function(e) {
            if (showPopup) {
                e.preventDefault();
                e.returnValue = '';
            }
        });";

        $this->AddJavascript($js);
    }
}
