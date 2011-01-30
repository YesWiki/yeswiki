<?php
/*
__hello.php
Appellé AVANT l'execution de l'action hello
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}



echo $this->Format("Je vais dire bonjour !");

?>
