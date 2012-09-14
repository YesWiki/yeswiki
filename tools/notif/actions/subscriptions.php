<?php
if ($user = $this->GetUser()) {

    echo "<b>Liste des pages ou vous &ecirc;tes abonn&eacute;s:</b><br /><br />\n" ;

    $my_pages_count = 0;
    $useremail = $user['email'];

    if ($pages = $this->LoadAll("SELECT * FROM ".$this->config["table_prefix"]."triplestriples WHERE property='subscriptions' AND value='$useremail'")) {

        foreach ($pages as $page) {
            echo $this->Format($page["resource"]),"<br />\n" ;
            $my_pages_count++;
        }
        
        if ($my_pages_count == 0) {
            echo "<i>Vous n'&ecirc;tes inscrit a aucune page.</i>";
        }
    }
}
else {
    echo "<i>Vous n'&ecirc;tes pas identifi&eacute; : impossible d'afficher la liste des pages ou vous &ecirc;tes abonn&eacute;s.</i>" ;
}
?> 
