<?php
/*
__hello.php
Appell� AVANT l'execution de l'action hello
*/

// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}



echo $this->Format("Je vais dire bonjour !");

?>
