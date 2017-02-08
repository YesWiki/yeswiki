<?php
/*
*/
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    if (isset($this->config['password_for_editing']) and !empty($this->config['password_for_editing']) and !$this->UserIsAdmin()) {
        if (!isset($_POST['password_for_editing'])
            or $_POST['password_for_editing'] != $this->config['password_for_editing']) {
            echo $this->Header();
            if (isset($_POST['password_for_editing'])
                and $_POST['password_for_editing'] != $this->config['password_for_editing']) {
                echo '<div class="alert alert-danger">Mauvais mot de passe.</div>';
            }
            if (isset($this->config['password_for_editing_message']) and !empty($this->config['password_for_editing_message'])) {
                echo '<p class="password_for_editing_message">'.$this->config['password_for_editing_message'].'</p>'."\n";
            }
            echo '<form method="post" action="'.$this->href('edit', $this->GetPageTag()).'" class="form-inline">
      <div class="form-group">
        <label for="password_for_editing">'._t('HASHCASH_GENERAL_PASSWORD').'</label>
        <input type="password" class="form-control" id="password_for_editing" name="password_for_editing">
      </div>';
            // pour l'edition d'une page de l'historique
            if (isset($_REQUEST['time'])) {
                echo '<input type="hidden" name="time" value="'.htmlspecialchars($_REQUEST['time']).'">';
            }
            echo '
      <button type="submit" class="btn btn-default">'._t('HASHCASH_SEND').'</button>
    </form>';
            echo $this->Footer();
            exit;
        }
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
        $(\'#ACEditor\').on(\'submit\', function() {
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
