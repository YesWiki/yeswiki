<?php
//vérification de sécurité
if (!eregi("wakka.php", $_SERVER['PHP_SELF'])) {
    die ("acc&egrave;s direct interdit");
}

?>
<div class="page">
<?php

if ($this->getUser()) {

	$us=$this->getUser();
        $useremail = $us['email'];

        $result = $this->LoadSingle("SELECT COUNT(*) as count FROM ".$this->config["table_prefix"]."triples WHERE ".
                "resource = '".mysql_escape_string($this->tag)."' AND ".
                "property  = '".mysql_escape_string('subscriptions')."' AND ".
                "value     = '".mysql_escape_string($useremail)."'");
                
        if ($result['count'] >= 1) {
                $msg = "Vous surveillez deja cette page.";
        }
        else {
            	$this->Query("insert into ".$this->config["table_prefix"]."triples set ".
                "resource = '".mysql_escape_string($this->tag)."', ".
                "property  = '".mysql_escape_string('subscriptions')."', ".
                "value     = '".mysql_escape_string($useremail)."' ");
                $msg = "Vous surveillez maintenant cette page.";
        }

	$this->SetMessage($msg);
	$this->Redirect($this->href());

}
?>
</div>
