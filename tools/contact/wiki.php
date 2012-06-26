<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (isset($metadatas['lang'])) { $wakkaConfig['lang'] = $metadatas['lang']; }
elseif (!isset($wakkaConfig['lang'])) { $wakkaConfig['lang'] = 'fr'; } 
include_once 'tools/contact/lang/contact_'.$wakkaConfig['lang'].'.inc.php';


//Savoir comment PEAR Mail envoie le message: "mail", "smtp" ou "sendmail"
define('CONTACT_MAIL_FACTORY', 'smtp');

?>