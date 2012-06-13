<?php

// Partie publique

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
//CONFIGURATION
//si 0 les admins ou le proprietaire d'une page doivent ouvrir les commentaires
//si 1 ils sont ouverts par defaut
define('COMMENTAIRES_OUVERTS_PAR_DEFAUT', 0);
define('CACHER_MOTS_CLES', 0);

$wiki  = new WikiTools($wakkaConfig);
$wikiClasses [] = 'Tags';
$wikiClassesContent [] = '
	function DeleteAllTags($page)
    {
		$tags = explode(" ", mysql_escape_string($liste_tags));
		//on recupere les anciens tags de la page courante
		$tabtagsexistants = $this->GetAllTriplesValues($page, \'http://outils-reseaux.org/_vocabulary/tag\', \'\', \'\');
		if (is_array($tabtagsexistants))
		{
			foreach ($tabtagsexistants as $tab)
			{
				$this->DeleteTriple($page, \'http://outils-reseaux.org/_vocabulary/tag\', $tab["value"], \'\', \'\');
			}
		}
		return;
	}

	function SaveTags($page, $liste_tags)
    {
		$tags = explode(",", mysql_escape_string($liste_tags));
		
		
		//on recupere les anciens tags de la page courante
		$tabtagsexistants = $this->GetAllTriplesValues($page, \'http://outils-reseaux.org/_vocabulary/tag\', \'\', \'\');
		if (is_array($tabtagsexistants))
		{
			foreach ($tabtagsexistants as $tab)
			{
				$tags_restants_a_effacer[] = $tab["value"];
			}
		}
		
		//on ajoute le tag s il n existe pas déjà
		foreach ($tags as $tag)
		{
			trim($tag);
			if ($tag!=\'\')
			{
				if (!$this->TripleExists($page, \'http://outils-reseaux.org/_vocabulary/tag\', $tag, \'\', \'\'))
				{
					$this->InsertTriple($page, \'http://outils-reseaux.org/_vocabulary/tag\', $tag, \'\', \'\');
				}
				//on supprime ce tag du tableau des tags restants a effacer
				if (isset($tags_restants_a_effacer)) unset($tags_restants_a_effacer[array_search($tag, $tags_restants_a_effacer)]);
			}			
		}

		//on supprime les tags restants a effacer
		if (isset($tags_restants_a_effacer))
		{
			foreach ($tags_restants_a_effacer as $tag)
			{
				$this->DeleteTriple($page, \'http://outils-reseaux.org/_vocabulary/tag\', $tag, \'\', \'\');
			}
		}
		return;
	}

	function GetAllTags($page=\'\')
	{
		if ($page==\'\')
		{
				$sql = \'SELECT DISTINCT value FROM \'.$this->config[\'table_prefix\'].\'triples WHERE property="http://outils-reseaux.org/_vocabulary/tag"\';
				return $this->LoadAll($sql);
		}
		else
		{
			return $this->GetAllTriplesValues($this->GetPageTag(), \'http://outils-reseaux.org/_vocabulary/tag\', \'\', \'\');
		}
	}

	function ParseQuery($string)
	{
		//$tab = array();
		//$tab[\'+\'] = preg_split("/\+([^-\+]+)/", $string);
		//$tab[\'-\'] = preg_split("/\-([^-\+]+)/", $string);	
		//var_dump($tab);	
		//return $tab;
		return $string;
	}

	function PageList($tags=\'\', $type=\'\', $nb=\'\', $tri=\'\', $template=\'\', $class=\'\', $lienedit=\'\')
	{
		if (isset($tags)) {
			//list($tags, $notags) = $this->ParseQuery($tags);
			$tags = $this->ParseQuery($tags);
		}
		if (isset($type))
		{
			//list($type, $notype) = $this->ParseQuery($type);
		}
		$req = \'\';
		$req_from = \'\';
		$req_having = \'\';

		//on fait les tableaux pour les tags, puis on met des virgules et des guillemets
		if (isset($tags) && $tags != \'\')
		{
			$req_from .= ", ".$this->config["table_prefix"]."triples tags ";
			$tags=trim($tags);
			$tab_tags = explode(",", $tags);
			$nbdetags = count($tab_tags);
			$tags = implode(",", $tab_tags);
			$tags = \'"\'.str_replace(\',\',\'","\',$tags).\'"\';
			$req .= \' AND tags.value IN (\'.$tags.\') \';
			$req .= \' AND tags.property="http://outils-reseaux.org/_vocabulary/tag" AND tags.resource=tag \';
			$req_having .= \' HAVING COUNT(tag)=\'.$nbdetags.\' \';
		}
/*
		if (isset($notags))
		{
			$notags=trim($notags);
			$tab_notags = explode(" ", $notags);
			$notags = implode(",", array_filter($tab_notags, "trim"));
			$notags = \'"\'.str_replace(\',\',\'","\',$notags).\'"\';
			$req .= \' AND NOT EXISTS (SELECT NULL FROM \'.$this->config["table_prefix"].\'triples notags WHERE notags.resource = tags.resource and notags.value IN (\'.$notags.\') AND notags.property="http://outils-reseaux.org/_vocabulary/tag" AND notags.resource=tag ) \';
		}


		//traitement du type de page
		if (isset($type))
		{
			$req_from .= ", ".$this->config["table_prefix"]."triples type ";
			$req .= \' AND type.resource=tag AND type.property="http://outils-reseaux.org/_vocabulary/type" AND type.value="\'.$type.\'" \';
		}
*/
		$req .= \' GROUP BY tag \';
		if ($req_having!=\'\') $req .= $req_having;

		//gestion du tri de l\'affichage
		if ($tri == "alpha")
		{
			$req .= \' ORDER BY tag ASC \';
		}
		elseif ($tri == "date")
		{
			$req .= \' ORDER BY time DESC \';
		}

		$requete = "SELECT DISTINCT tag, time, user, owner, body FROM ".$this->config["table_prefix"]."pages".$req_from." WHERE latest = \'Y\' and comment_on = \'\' ".$req;

		return $this->LoadAll($requete);		
	}
';
?>
