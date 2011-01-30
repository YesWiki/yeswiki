<?php

// Partie publique 

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
$wakkaConfig['menu_page'] = 'PageMenu';


// Surcharge  fonction  LoadRecentlyChanged : suppression remplissage cache car affecte le rendu de la navigation.
$wikiClasses [] = 'Navigation';
$wikiClassesContent [] = ' 

	function LoadRecentlyChanged($limit=50)
        {
                $limit= (int) $limit;
                if ($pages = $this->LoadAll("select id, tag, time, user, owner from ".$this->config["table_prefix"]."pages where latest = \'Y\' and comment_on =  \'\' order by time desc limit $limit"))
                {
                        return $pages;
                }
        }


	
';	

?>
