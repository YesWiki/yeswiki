<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// inclusion de la langue
if (file_exists('tools/contact/lang/contact_'.$wakkaConfig['lang'].'.inc.php')) {
	include_once 'tools/contact/lang/contact_'.$wakkaConfig['lang'].'.inc.php';
} else {
	include_once 'tools/contact/lang/contact_fr.inc.php';
}

//Savoir comment PEAR Mail envoie le message: "mail", "smtp" ou "sendmail"
define('CONTACT_MAIL_FACTORY', 'mail');

?>