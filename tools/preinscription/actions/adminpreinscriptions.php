<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$max = $this->GetParameter('max');
if (empty($max)) die("Action preinscription param&egrave;te max obligatoire.");

if ($this->UserIsAdmin())
{
    echo "<b>Liste des préinscriptions en cours</b><br /><br />\n" ;
    $req = "SELECT value,resource FROM ".$this->config["table_prefix"]."triples WHERE property='http://outils-reseaux.org/_vocabulary/preinscription' ORDER BY resource ASC";
    if ($pages = $this->LoadAll($req)) 
    {    	
    	$tab = array();
        foreach ($pages as $page) {
        	$resultat = explode('|',trim($page["value"]));
        	$tab[$page["resource"]][] = $resultat;
        }
        foreach ($tab as $clef => $valeur) 
        {        		
                echo '<h3>'.$clef.'</h3>';
                if (is_array($valeur)) 
                {
                	$total = 0;
                	$liste = '<ul>'."\n";              
                	foreach ($valeur as $clef2 => $valeur2) 
                	{           		
                		if (is_array($valeur2)) 
                		{
                			$liste .=  '<li><strong>'.$valeur2[1].' '.$valeur2[2].'</strong> - <a href="mailto:'.$valeur2[0].'" title="Envoyer un mail">'.$valeur2[0].'</a> - Tarif : '.$valeur2[3].' euros';
                			$liste .= '&nbsp;-&nbsp;<a href="'.$this->href('', $clef).'/depreinscription&amp;email='.$valeur2[0].'&amp;page='.$this->tag.'">Effacer</a></li>'."\n";
                			$total = $total + $valeur2[3];				
                		}                				
                	}
                	$liste .= '</ul>'."\n";
                	if ($total > 0)
					{						
						$pourcentage= round($total/$max*100) ;						
						if ($pourcentage>100) $pourcentage = 100;						
						$jauge ='<span class="jauge" style="font:10px verdana;">Remplissage :
						<img src="tools/preinscription/presentation/compteur.php?pc='.$pourcentage.'" />
						</span>';
					}	
					echo $jauge."\n".'<br />'.$liste."\n";
                }                
        }        
	}
	else 
	{
	    	echo $this->Format("//Aucune préinscription trouvée//");
	}
}
else
{
	echo $this->Format("//L'action adminpreinscription est r&eacute;serv&eacute;e au groupe des administrateurs...//");
}
?> 
