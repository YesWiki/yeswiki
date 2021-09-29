<?php
/*
*/

use YesWiki\Security\Controller\SecurityController;
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    $securityController = $this->services->get(SecurityController::class);
    list($state,$message) = $securityController->isGrantedPasswordForEditing();
    if (!$state){
        echo $this->Header().
            $message.
            $this->Footer();
        exit;
    }
  
    if ($this->config['use_hashcash']) {
        if (isset($_POST['submit']) && $_POST['submit'] == 'Sauver') {
            require_once 'tools/security/secret/wp-hashcash.lib';
            if (!isset($_POST['hashcash_value']) || $_POST['hashcash_value'] != hashcash_field_value()) {
                $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('HASHCASH_ERROR_PAGE_UNSAVED').'</div>';
                $_POST['submit'] = '';
            }
        }
    }

    if ($this->config['use_captcha']) {
        if (isset($_POST['submit']) && $_POST['submit'] == 'Sauver') {
            define("CAPTCHA_INCLUDE", true);
            include_once 'tools/security/captcha.php';
            if (empty($_POST['captcha'])) {
                $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('CAPTCHA_ERROR_PAGE_UNSAVED').'</div>';
                $_POST['submit'] = '';
            } elseif (!empty($_POST['captcha'])) {
                $wdcrypt = cryptWord($_POST['captcha']);
                if ($wdcrypt != $_POST['captcha_hash']) {
                    $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('CAPTCHA_ERROR_WRONG_WORD').'</div>';
                    $_POST['submit'] = '';
                }
            }
        }
    }

    if ($this->config['use_alerte']) {
        $js = '// par défaut, pas de popup d\'alerte pour quitter la page
        var showPopup = 0;

        // on demande a faire apparaitre la popup si la page a été modifiée
        $(\'#body\').on(\'input selectionchange propertychange\', function() {
          showPopup = 1;
        });

        // on annule la popup si l\'on sauve la page
        $(\'#ACEditor, #formulaire\').on(\'submit\', function() {
          showPopup = 0;
        });

        // si l\'on quitte la page, on affiche la popup si besoin
        $(window).on(\'beforeunload\', function(e) {
          if (showPopup) {
            return true;
          }
        });';

        $this->AddJavascript($js);
    }
}
