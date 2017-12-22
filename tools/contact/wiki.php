<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

//Savoir comment le mail envoie le message: "mail", "sendmail" ou "smtp"
$wakkaConfig['contact_mail_func'] = isset($wakkaConfig['contact_mail_func']) ?
  $wakkaConfig['contact_mail_func']
  :'mail';

// /!\ A ne remplir que si l'on veut envoyer par smtp
// serveur smtp hote
$wakkaConfig['contact_smtp_host'] = isset($wakkaConfig['contact_smtp_host']) ?
  $wakkaConfig['contact_smtp_host']
  :'';

// port smtp utilisé
$wakkaConfig['contact_smtp_port'] = isset($wakkaConfig['contact_smtp_port']) ?
  $wakkaConfig['contact_smtp_port']
  :'';

// utilisateur smtp
$wakkaConfig['contact_smtp_user'] = isset($wakkaConfig['contact_smtp_user']) ?
  $wakkaConfig['contact_smtp_user']
  :'';

// mot de passe smtp
$wakkaConfig['contact_smtp_pass'] = isset($wakkaConfig['contact_smtp_pass']) ?
  $wakkaConfig['contact_smtp_pass']
  :'';

// template de base pour les mails html
$wakkaConfig['mail_template'] = isset($wakkaConfig['mail_template']) ?
  $wakkaConfig['mail_template']
  :'email.tpl.html';

// debug mode (0 pour rien, 1 pour normal, 2 pour detaille)
$wakkaConfig['contact_debug'] = isset($wakkaConfig['contact_debug']) ?
  $wakkaConfig['contact_debug']
  : 0;
