<?php
/*
hello.php
Nouvel handler  'hello' ou remplace le handler hello si d�j� pr�sent
*/


// V�rification de s�curit�
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
