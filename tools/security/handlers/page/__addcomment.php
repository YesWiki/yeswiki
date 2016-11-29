<?php

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if (isset($_POST["action"]) && $_POST["action"] == 'addcomment') {
    if ($this->config['use_hashcash']) {
        require_once('tools/security/secret/wp-hashcash.lib');
        if (!isset($_POST["hashcash_value"]) || ($_POST["hashcash_value"] != hashcash_field_value())) {
            $this->SetMessage(_t('HASHCASH_COMMENT_NOT_SAVED_MAYBE_YOU_ARE_A_ROBOT'));
            $this->redirect($this->href());
        }
    }

    if ($this->config['use_nospam']) {
        if (!isset($_SESSION['nospam'])) {
            die("Vous avez été trop long.");
        }

        $nospam = $_SESSION['nospam'];

        if (!array_key_exists($nospam['nospam1'], $_POST)) {
            die("NoSpam : Commentaire refusé");
        } else if ($_POST[$nospam['nospam1']] != "") {
            die("NoSpam : Commentaire refusé");
        } else if (!array_key_exists($nospam['nospam2'], $_POST)) {
            die("NoSpam : Commentaire refusé");
        } else if ($_POST[$nospam['nospam2']] != $nospam['nospam2-val']) {
            die("NoSpam : Commentaire refusé");
        } else if (!array_key_exists('nxts', $_POST) || !array_key_exists('nxts_signed', $_POST)) {
            die("NoSpam : Commentaire refusé");
        } else if (sha1($_POST['nxts'] . $nospam['salt']) != $_POST['nxts_signed']) {
            die("NoSpam : Commentaire refusé");
        } else if (time() < $_POST['nxts'] + 15) {
            die("NoSpam : trop rapide");
        }
    }
}
