<?php
/*
__hello.php
Appelle AVANT le  handler  'hello' 
*/


// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

echo $this->Header();
echo $this->Format("===== Je vais dire Bonjour !=====");


?>
<div class="page">
