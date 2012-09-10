<?php
// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// Surcharge methode SavePage de la class Wiki

$wikiClasses [] = 'Notif';
$wikiClassesContent [] = ' 
	function SavePage($tag, $method = "", $text = "", $track = 1) {
	  	Wiki::SavePage($tag, $method, $text, $track);	
	          // Email watchers
        	 $results = $this->LoadAll("SELECT value FROM ".$this->config["table_prefix"]."triples WHERE ".
                "property = \"".mysql_real_escape_string("subscriptions")."\" AND ".
                "resource = \"".mysql_real_escape_string($tag)."\" ");
                
                $currentuser = $this->GetUser();
                $currentuseremail = $currentuser["email"]; 
        	$currentusername  = $currentuser["name"]; 
        	foreach ($results as $record) {
            	$useremail = $record["value"];
                // Ne pas envoyer d email a la personne qui viens de modifier la page.
                 if ($currentuseremail != $useremail) {
                  $url = $this->config["base_url"].$tag;
                  $unwatch = $this->config["base_url"]."$tag/unsubscribe";
        
                  $msg = "
                  Bonjour,
        
                  La page $url a ete modifiee par $currentusername.
        
                  Si vous ne voulez plus surveiller cette page rendez vous ici: $unwatch 
        
                  @bientot
                   ";  
                  mail($useremail,$this->config["wakka_name"]." la page $tag a ete modifiee !",$msg);
                  }
                }
        }		
';
?>
