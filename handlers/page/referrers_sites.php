<?php
/*
wakka.php
Copyright (c) 2003, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright  2003  Eric FELDSTEIN
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

ob_start();
?>
<div class="page">
<?php
// Valeur par défaut du paramétre "global"
$global = !empty($_REQUEST['global']);
// Si le paramétre "global" a été spécifié
if ($global)
{
	$title = "Domaines faisant r&eacute;f&eacute;rence &agrave; ce wiki (<a href=\"".$this->href("referrers", "", "global=1")."\">voir la liste des pages externes</a>):";
	$referrers = $this->LoadReferrers();
}
else
{
	$title = "Domaines faisant r&eacute;f&eacute;rence &agrave; ".$this->Link($this->GetPageTag()).
		($this->GetConfigValue("referrers_purge_time") ? " (depuis ".($this->GetConfigValue("referrers_purge_time") == 1 ? "24 heures" : $this->GetConfigValue("referrers_purge_time")." jours").")" : "")." (<a href=\"".$this->href("referrers")."\">voir la liste des pages externes</a>):";
	$referrers = $this->LoadReferrers($this->GetPageTag());
}

echo "<b>$title</b><br /><br />\n" ;
if ($referrers)
{
	for ($a = 0; $a < count($referrers); $a++)
	{
		$temp_parse_url = parse_url($referrers[$a]["referrer"]);
		$temp_parse_url = (!empty($temp_parse_url["host"])) ? strtolower(preg_replace("/^www\./Ui", "", $temp_parse_url["host"])) : "inconnu";

		if (isset($referrer_sites["$temp_parse_url"]))
		{
			$referrer_sites["$temp_parse_url"] += $referrers[$a]["num"];
		}
		else
		{
			$referrer_sites["$temp_parse_url"] = $referrers[$a]["num"];
		}
	}

	array_multisort($referrer_sites, SORT_DESC, SORT_NUMERIC);
	reset($referrer_sites);

	echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n" ;
	foreach ($referrer_sites as $site => $site_count)
	{
		echo "<tr>" ;
		echo "<td width=\"30\" align=\"right\" valign=\"top\" style=\"padding-right: 10px\">" , $site_count, "</td>" ;
		echo "<td valign=\"top\">" , (($site != "unknown") ? "<a href=\"http://$site\">$site</a>" : $site) , "</td>" ;
		echo "</tr>\n" ;
	}
	echo "</table>\n" ;
}
else
{
	echo "<i>None</i><br />\n" ;
}

if ($global)
{
	echo "<br />[<a href=\"",$this->href("referrers_sites"),"\">Voir les domaines faisant r&eacute;f&eacute;rence &agrave; ",$this->GetPageTag()," seulement</a> | <a href=\"",$this->href("referrers"),"\">Voir les r&eacute;f&eacute;rences ",$this->GetPageTag()," seulement</a>]";
}
else
{
	echo "<br />[<a href=\"",$this->href("referrers_sites", "", "global=1"),"\">Voir tous les domaines faisant r&eacute;f&eacute;rence </a> | <a href=\"",$this->href("referrers", "", "global=1"),"\">Voir toutes les r&eacute;f&eacute;rences </a>]" ;
}


?>
</div>
<?php

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();

?>