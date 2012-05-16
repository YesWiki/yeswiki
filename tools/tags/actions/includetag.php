<?php
/*
includetag.php

Copyright 2009  Florian SCHMITT
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// recuperation de tous les tags
$tags = $this->GetParameter('with');
if (empty($tags))
{
	die ('<span class="error">Action includetag : param&egrave;tre with obligatoire.</span>');
}

$notags = $this->GetParameter('without');
$lienedit = $this->GetParameter('edit');
$class = $this->GetParameter('class');
$tri = $this->GetParameter('tri');
$nb = $this->GetParameter('nb');

//on fait les tableaux pour les tags, puis on met des virgules et des guillemets
$tags = implode(",", array_filter(explode(" ", $tags), "trim"));
$tags = '"'.str_replace(',','","',$tags).'"';
if (!empty($notags))
{
	$notags = implode(",", array_filter(explode(" ", $notags), "trim"));
	$notags = '"'.str_replace(',','","',$notags).'"';
}

$req =' AND value IN ('.$tags.')';
if (!empty($notags))
{
	$req .= ' AND value NOT IN ('.$notags.')';
}
$req .= ' AND property="http://outils-reseaux.org/_vocabulary/tag" AND resource=tag ';

//gestion du tri de l'affichage
if (!empty($tri))
{
	if ($tri == "alpha")
	{
		$req .= ' ORDER BY tag ASC';
	}
	elseif ($tri == "date")
	{
		$req .= ' ORDER BY time DESC';
	}
}
//par defaut on tri par date
else
{
		$req .= ' ORDER BY time DESC';
}

$requete = "SELECT DISTINCT tag, time, user, owner, body FROM ".$this->config["table_prefix"]."pages, ".$this->config["table_prefix"]."triples WHERE latest = 'Y' and comment_on = '' ".$req;


if ($pages = $this->LoadAll($requete)) {
	foreach ($pages as $page)
	{
		if ( $this->tag!=$page['tag'] )
		{
			$text = '{{include page="'.$page['tag'].'"';
			if (!empty($lienedit))
			{
				$text .= ' edit="'.$lienedit.'"';
			}
			if (!empty($class))
			{
				$text .= ' class="'.$class.'"';
			}
			$text .=' }}';
			echo $this->Format($text);
		}
	}
}
