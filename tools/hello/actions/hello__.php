<?php
/*
hello__.php
Appellé APRES l'execution de l'action hello
*/


// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


echo $this->Format("J'ai dit bonjour !");

?>
