<?php
/*
hello.php
Nouvelle action 'hello' ou remplace l'action hello si d�j� pr�sente
*/

// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}



echo $this->Format("Bonjour !");

?>
