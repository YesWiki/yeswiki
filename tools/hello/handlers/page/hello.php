<?php
/*
hello.php
Nouvel handler  'hello' ou remplace le handler hello si déjà présent
*/


// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


echo $this->Format("===== Bonjour !=====");

if ($this->HasAccess("read"))
{
        if (!$this->page)
        {
                return;
        }
        else
        {
                echo $this->Format($this->page["body"]);
        }
}


?>
