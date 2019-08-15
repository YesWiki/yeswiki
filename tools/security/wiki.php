<?php
// Partie publique

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// initialisation des differentes options de sécurité
// alerte pour quitter le mode édition
if (!isset($wakkaConfig['use_alerte'])) {
    $wakkaConfig['use_alerte'] = true;
}
// hashcash pour le mode edition
if (!isset($wakkaConfig['use_hashcash'])) {
    $wakkaConfig['use_hashcash'] = true;
}
// antispam nospam pour commentaires
if (!isset($wakkaConfig['use_nospam'])) {
    $wakkaConfig['use_nospam'] = false;
}
// recaptcha
if (!isset($wakkaConfig['use_captcha'])) {
    $wakkaConfig['use_captcha'] = false;
}
