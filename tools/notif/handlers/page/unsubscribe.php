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

        if ($result['count'] == 0) {
                $msg = "Vous ne surveillez pas cette page.";
        }
        else {
               $this->Query("DELETE FROM ".$this->config["table_prefix"]."triples WHERE ".
                "resource = '".mysql_escape_string($this->tag)."' AND ".
                "property  = '".mysql_escape_string('subscriptions')."' AND ".
                "value     = '".mysql_escape_string($useremail)."'");
                $msg = "Vous ne surveillez plus cette page.";
        }
   $this->SetMessage($msg);
   $this->Redirect($this->href());
}
?>
</div>
