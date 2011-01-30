<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

//Savoir comment PEAR Mail envoie le message: "mail", "smtp" ou "sendmail"
define('CONTACT_MAIL_FACTORY', 'smtp');