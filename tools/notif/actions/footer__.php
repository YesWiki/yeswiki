<?php

$user = $this->getUser();
if ($this->getUser()) {
 $user = $this->getUser();
 $useremail=$user['email'];
 $result = $this->LoadSingle("SELECT COUNT(*) as count FROM ".$this->config["table_prefix"]."triples WHERE ".
    "resource = '".mysql_real_escape_string($this->tag)."' AND ".
    "property  = '".mysql_real_escape_string('subscriptions')."' AND ".
    "value     = '".mysql_real_escape_string($useremail)."'");
                
    if ($result['count'] >= 1)
        {
        $msg = "<a href=\"".$this->href("unsubscribe")."\" title=\"Cliquez pour ne plus recevoir les derni&egrave;res modifications sur cette page.\">Se desabonner </a>:: ";
        }
    else
        {
        $msg = "<a href=\"".$this->href("subscribe")."\" title=\"Cliquez pour recevoir les derni&egrave;res modifications sur cette page.\">S'abonner </a>:: ";
        }

	$plugin_output_new=preg_replace ('/Recherche : /',$msg.'Recherche :', $plugin_output_new);

}

