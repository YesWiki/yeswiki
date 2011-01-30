<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
//barre de redaction

if ($this->HasAccess("write")) {
	//action pour le footer de wikini
	$wikini_barre_actions =	'<div class="barre_actions">'."\n";
	if ( $this->HasAccess("write") ) {
		$wikini_barre_actions .= "<a class=\"lien_modifier\" href=\"".$this->href("edit")."\" title=\"&Eacute;diter cette page.\">&Eacute;diter</a>\n";
	}
	if (($this->UserIsOwner()) || ($this->UserIsAdmin())) {
		$wikini_barre_actions .= "<a class=\"lien_supprimer\" rel=\"#overlay-action\" href=\"".$this->href("deletepage")."\" title=\"Supprimer cette page et tout son historique.\">Supprimer</a>\n";
	}
	if ( $this->GetPageTime() ) {
		$wikini_barre_actions .= "<a class=\"lien_historique\" rel=\"#overlay-action\" href=\"".$this->href("revisions")."\" title=\"Voir l'historique des modifications de cette page.<br />Dernière modification : ".
				date("\l\e m.d.Y \à H:i:s",strtotime($this->GetPageTime())).".\">Historique</a>\n";
	}
	// if this page exists
	if ($this->page)
	{
		
		// if owner is current user
		if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
		{
			$infodroits = "Propri&eacute;taire&nbsp;: vous";
		}
		else
		{
			if ($owner = $this->GetPageOwner())
			{
				$infodroits = "Propri&eacute;taire : $owner";
			}
			else
			{
				$infodroits = "Pas de propri&eacute;taire ";
			}
		}
		$wikini_barre_actions .= "<a class=\"lien_droits\" rel=\"#overlay-action\" href=\"".$this->href("acls")."\" title=\"&Eacute;diter les droits de cette page.<br />".$infodroits."\">Droits</a>\n";
	}
	$wikini_barre_actions .=
	'<a class="lien_referrer" rel="#overlay-action" href="'.$this->href("referrers").'" title="Voir les URLs faisant r&eacute;f&eacute;rence &agrave; cette page.">'."\n".
	'R&eacute;f&eacute;rences</a>'."\n".
	"<a class=\"lien_share\" rel=\"#overlay-action\" href=\"".$this->href("share")."\" title=\"Voir les possibilit&eacute;s de partage de cette page.\">Partage</a>\n".
	'</div>'."\n";
	
	echo $wikini_barre_actions;	
}

?>
