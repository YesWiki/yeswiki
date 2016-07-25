<?php
/*
*/
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if (isset($this->config['password_for_editing']) and !empty($this->config['password_for_editing'])) {
    if (!isset($_POST['password_for_editing'])
        or $_POST['password_for_editing'] != $this->config['password_for_editing']) {
        echo $this->Header();
        if (isset($_POST['password_for_editing'])
            and $_POST['password_for_editing'] != $this->config['password_for_editing']) {
            echo '<div class="alert alert-danger">Mauvais mot de passe.</div>';
        }
        echo '<form method="post" action="'.$this->href('edit', $this->GetPageTag()).'" class="form-inline">
  <div class="form-group">
    <label for="password_for_editing">Entrer le mot de passe général pour l\'édition :</label>
    <input type="password" class="form-control" id="password_for_editing" name="password_for_editing">
  </div>
  <button type="submit" class="btn btn-default">Envoyer</button>
</form>';
        echo $this->Footer();
        exit;
    }
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    if (isset($_POST['submit']) && $_POST['submit'] == 'Sauver') {
        require_once 'tools/hashcash/secret/wp-hashcash.lib';
        if (!isset($_POST['hashcash_value']) || $_POST['hashcash_value'] != hashcash_field_value()) {
            $error = '<div class="alert alert-danger"><a href="#" data-dismiss="alert" class="close">&times;</a>'._t('HASHCASH_ERROR_PAGE_UNSAVED').'</div>';
            $_POST['submit'] = '';
        }
    }
}
