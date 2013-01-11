<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$max = $this->GetParameter('max');
if (empty($max)) die("Action preinscription param&egrave;te max obligatoire.");

if ($user = $this->GetUser()) {
    echo "<b>Liste des formations o&ugrave; vous &ecirc;tes pr&eacute;-inscrit:</b><br /><br />\n" ;
    $useremail = $user['email'];
	$req = "SELECT resource FROM ".$this->config["table_prefix"]."triples WHERE property='http://outils-reseaux.org/_vocabulary/preinscription' AND value LIKE '$useremail%'";
    if ($pages = $this->LoadAll($req)) {
    	echo '<ul>'."\n";
        foreach ($pages as $page) {
        	echo '<li>'."\n".$this->Format($page["resource"]);
	       		$sql = 'SELECT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/preinscription" AND resource="'.$page["resource"].'"';
				$result = $this->LoadAll($sql);
				$total = 0;
				if (is_array($result))
				{
					$tableau = array();	
					foreach ($result as $tab)
					{
						$resultat = explode('|',trim($tab["value"]));
						$total = $total + $resultat[3];	
					}					
					if ($total > 0)
					{						
						$pourcentage= round($total/$max*100) ;						
						if ($pourcentage>100) $pourcentage = 100;						
						echo '&nbsp;-&nbsp;<span class="jauge" style="font:10px verdana;">Remplissage :
					<img src="tools/preinscription/presentation/compteur.php?pc='.$pourcentage.'" />
					</span>';
					}				
				}
				echo '&nbsp;-&nbsp;<a href="'.$this->href('', $page["resource"]).'/depreinscription&amp;email='.$useremail.'">Se d&eacute;sinscrire</a>';
				echo '</li>'."\n";           
        }
        echo '</ul>'."\n";
    } else {
    	echo "<i>Vous n'&ecirc;tes inscrit a aucune formation.</i>";
    }
}
else {	
	if (!isset($_REQUEST['email'])) {
		echo '<b>Entrez votre adresse de messagerie pour voir les formations auxquelles vous êtes inscrit.</b><br /><br />';
		echo $this->formOpen('','');
		echo '<label>Adresse mail&nbsp;:&nbsp;</label><input maxlength="100" name="email" type="text" ';
		if (isset($_REQUEST['email'])) {
			echo 'value="'.$_REQUEST['email'].'" ';
		}
		echo '/><input name="Envoyer" value="Envoyer" type="submit">';
		echo $this->formClose();
	} else {
		echo '<b>Liste des formations auxquelles vous êtes incrit avec le mail '.$_REQUEST['email'].'</b><br /><br />';
		$req = "SELECT resource FROM ".$this->config["table_prefix"]."triples WHERE property='http://outils-reseaux.org/_vocabulary/preinscription' AND value LIKE '".$_REQUEST['email']."%'";
	    if ($pages = $this->LoadAll($req)) {
	    	echo '<ul>'."\n";			
	        foreach ($pages as $page) {
	            echo '<li>'."\n".$this->Format($page["resource"]);
	       		$sql = 'SELECT value FROM '.$this->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/preinscription" AND resource="'.$page["resource"].'"';
				$result = $this->LoadAll($sql);
				$total = 0;
				if (is_array($result))
				{
					$tableau = array();	
					foreach ($result as $tab)
					{
						$resultat = explode('|',trim($tab["value"]));
						$total = $total + $resultat[3];	
					}					
					if ($total > 0)
					{						
						$pourcentage= round($total/$max*100) ;						
						if ($pourcentage>100) $pourcentage = 100;						
						echo '&nbsp;-&nbsp;<span class="jauge" style="font:10px verdana;">Remplissage :
					<img src="tools/preinscription/presentation/compteur.php?pc='.$pourcentage.'" />
					</span>';
					}				
				}
				echo '&nbsp;-&nbsp;<a href="'.$this->href('', $page["resource"]).'/depreinscription&amp;email='.$_REQUEST['email'].'">Se d&eacute;sinscrire</a>';
				echo '</li>'."\n";
	        }
	        echo '</ul>'."\n";	       
	    } else {
	        echo "<i>Vous n'&ecirc;tes inscrit a aucune formation.</i>";	    
	    }
	    echo '<br /><a href="'.$this->href().'" title="Lancer une nouvelle recherche">Lancer une nouvelle recherche</a><br />';
	}	    	
}
?> 
