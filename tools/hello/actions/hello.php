<?php
/*
hello.php
Nouvelle action 'hello' ou remplace l'action hello si déjà présente
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}



echo $this->Format("Bonjour !");

?>
