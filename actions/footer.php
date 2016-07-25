<?php
/*
$Id: footer.php 804 2007-07-26 19:45:21Z lordfarquaad $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003, 2004 David DELON
Copyright 2002, 2003, 2004 Charles NEPOTE
Copyright 2002, 2003  Patrick PAUL
Copyright 2003  Eric DELORD
Copyright 2004  Jean Christophe ANDRE
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
$search_page = $this->href("", "RechercheTexte", "");
$edit_link = $this->HasAccess("write") ? "<a href=\"".$this->href("edit")."\" title=\"Cliquez pour &eacute;diter cette page.\">&Eacute;diter cette page</a> ::\n" : "";
$revisions_link = $this->GetPageTime() ? "<a href=\"".$this->href("revisions")."\" title=\"Cliquez pour voir les derni&egrave;res modifications sur cette page.\">".$this->GetPageTime()."</a> ::\n" : "";
$owner_info = '';
// if this page exists
if ($this->page)
{
	// if owner is current user
	if ($this->UserIsOwner())
	{
		$owner_info = "Propri&eacute;taire&nbsp;: vous :: \n";
	}
	else
	{
		if ($owner = $this->GetPageOwner())
		{
			$owner_info = "Propri&eacute;taire : " . $this->Format($owner);
		}
		else
		{
			$owner_info = "Pas de propri&eacute;taire ";
			$owner_info .= ($this->GetUser() ? "(<a href=\"".$this->href("claim")."\">Appropriation</a>)" : "");
		}
		$owner_info .= " :: \n";
	}
	if ($this->UserIsOwner() || $this->UserIsAdmin())
	{
		$owner_info .=
		"<a href=\"" . $this->href("acls") . "\" title=\"Cliquez pour &eacute;diter les permissions de cette page.\">&Eacute;diter permissions</a> :: \n" .
		"<a href=\"" . $this->href("deletepage") . "\">Supprimer</a> :: \n";
	}
}
$backlinks = $this->href('backlinks');
$carto = $this->href('svg');
$xhtml_validation_link = 'http://validator.w3.org/check?uri=' . urlencode($this->href());
$css_validation_link = 'http://jigsaw.w3.org/css-validator/validator?uri=' . urlencode($this->href());
$wikini_site_url = $this->Link("WikiNi:PagePrincipale", "", "WikiNi ".$this->GetWikiNiVersion());
$debug_log = '';
if ($this->GetConfigValue("debug")=="yes")
{
	$debug_log = "<span class=\"debug\"><b>Query log :</b><br />\n";
	$T_SQL=0;
	foreach ($this->queryLog as $query)
	{
		$debug_log .= $query["query"]." (".round($query["time"],4).")<br />\n";
		$T_SQL = $T_SQL + $query["time"];
	}
	$debug_log .= "</span>\n";

	$debug_log .= "<span class=\"debug\">".round($T_SQL, 4)." s (total SQL time)</span><br />\n";

	define ('T_END', microtime(true));
	$debug_log .= "<span class=\"debug\"><b>".round(T_END-T_START, 4)." s (total time)</b></span><br />\n";

	$debug_log .= "<span class=\"debug\">SQL time represent : ".round((($T_SQL/(T_END-T_START))*100),2)."% of total time</span>\n";
}

?>

	<form action="<?php echo $search_page ?>" method="get">
		<div class="footer">
			<input type="hidden" name="wiki" value="RechercheTexte" />
			<?php echo $edit_link, $revisions_link, $owner_info ?>
			
			<a href="<?php echo $backlinks ?>" title="Pages faisant r&eacute;f&eacute;rence &agrave; cette page.">R&eacute;tro-liens</a> ::
			<a href="<?php echo $carto ?>" title="Cartographie des pages liées à cette page (nécessite SVG).">Cartographie</a> ::
			Recherche : <input name="phrase" size="15" class="searchbox" />
		</div>
	</form>
	
	<div class="copyright">
		<a href="<?php echo $xhtml_validation_link ?>">XHTML 1.0 valide ?</a> ::
		<a href="<?php echo $css_validation_link ?>">CSS valide ?</a> ::
		-- Fonctionne avec <?php echo $wikini_site_url ?>
	</div>
	
	<?php echo $debug_log ?>
	
	</body>
</html>
