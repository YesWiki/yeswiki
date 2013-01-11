</div>
<?php
/*
hello__.php
Appelle APRES le  handler  'hello' 
*/


// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

echo $this->Format("===== J'ai dit Bonjour !=====");


echo $this->Footer();


?>
