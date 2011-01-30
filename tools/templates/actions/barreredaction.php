<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
//barre de redaction

if ($this->HasAccess("write")) {
	//action pour le footer de wikini
	$wikini_barre_bas =
	'<div class="footer">'."\n";
	if ( $this->HasAccess("write") ) {
		$wikini_barre_bas .= "<a class=\"link-edit\" href=\"".$this->href("edit")."\" title=\"Cliquez pour &eacute;diter cette page.\">&Eacute;diter cette page</a> ::\n";
	}
	if ( $this->GetPageTime() ) {
		$wikini_barre_bas .= "<a rel=\"#overlay-link\" class=\"link-revisions\" href=\"".$this->href("revisions")."\" title=\"Cliquez pour voir les derni&egrave;res modifications sur cette page.\">".$this->GetPageTime()."</a> ::\n";
	}
	// if this page exists

	if ($this->page)
    {   
                // if owner is current user
                if ($this->UserIsOwner() )   
                {   
                        $wikini_barre_bas .=
                        "Propri&eacute;taire&nbsp;: vous :: \n";
                }
                else
                {   
                        if ($owner = $this->GetPageOwner())
                        {
                                $wikini_barre_bas .= "Propri&eacute;taire : ".$this->Format($owner);
                        }   
                        else
                        {   
                                $wikini_barre_bas .= "Pas de propri&eacute;taire ";
                                $wikini_barre_bas .= ($this->GetUser() ? "(<a href=\"".$this->href("claim")."\">Appropriation</a>)" : "");
                        }
                        $wikini_barre_bas .= " :: \n";
                }

                if ($this->UserIsOwner() || $this->UserIsAdmin()) { 
                        $wikini_barre_bas .=
                        "<a rel=\"#overlay-link\" class=\"link-acls\" href=\"".$this->href("acls")."\" title=\"Cliquez pour &eacute;diter les permissions de cette page.\">Permissions</a> :: \n".
                        "<a rel=\"#overlay-link\" class=\"link-delete\" href=\"".$this->href("deletepage")."\">Supprimer</a> :: \n";
                }   

      }   
	
	
	
	$wikini_barre_bas .=
	'<a rel="#overlay-link" class="link-referrers" href="'.$this->href("referrers").'" title="Cliquez pour voir les URLs faisant r&eacute;f&eacute;rence &agrave; cette page.">'."\n".
	'R&eacute;f&eacute;rences</a>'."\n".
	" :: <a class=\"link-diaporama\" href=\"".$this->href("diaporama")."\" title=\"Lancer cette page en mode diaporama.\">Diaporama</a>\n".
	" :: <a rel=\"#overlay-link\" class=\"link-share\" href=\"".$this->href("share")."\" title=\"Voir les possibilit&eacute;s de partage de cette page.\">Partager</a>\n".
	'</div>'."\n";
	
	echo $wikini_barre_bas;	
}

?>
