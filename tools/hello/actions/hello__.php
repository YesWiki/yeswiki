<?php
/*
hello__.php
Appell� APRES l'execution de l'action hello
*/


// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


echo $this->Format("J'ai dit bonjour !");

?>
