<?php
/*
__hello.php
Appelle AVANT le  handler  'hello' 
*/


// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

echo $this->Header();
echo $this->Format("===== Je vais dire Bonjour !=====");


?>
<div class="page">
