<?php
/*
$Id: show.php,v 1.3 2010-03-04 14:17:59 mrflos Exp $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2003  Eric DELORD
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRÉ
Copyright 2005  Didier Loiseau
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

// Generate page before displaying the header, so that it might interract with the header
ob_start();

echo '<div class="page"';
echo (($user = $this->GetUser()) && ($user['doubleclickedit'] == 'N') || !$this->HasAccess('write')) ? '' : ' ondblclick="doubleClickEdit(event);"';
echo '>'."\n";
if (!empty($_SESSION['redirects']))
{
	$trace = $_SESSION['redirects'];
	$tag = $trace[count($trace) - 1];
	$prevpage = $this->LoadPage($tag);
	echo '<div class="redirectfrom"><em>(Redirig&eacute; depuis ', $this->Link($prevpage['tag'], 'edit'), ")</em></div>\n";
}

if ($HasAccessRead=$this->HasAccess("read"))
{
	if (!$this->page)
	{
		echo "Cette page n'existe pas encore, voulez vous la <a href=\"".$this->href("edit")."\">cr&eacute;er</a> ?" ;
	}
	else
	{
		// comment header?
		if ($this->page["comment_on"])
		{
			echo "<div class=\"commentinfo\">Ceci est un commentaire sur ",$this->ComposeLinkToPage($this->page["comment_on"], "", "", 0),", post&eacute; par ",$this->Format($this->page["user"])," &agrave; ",$this->page["time"],"</div>";
		}

		if ($this->page["latest"] == "N")
		{
			echo "<div class=\"revisioninfo\">Ceci est une version archiv&eacute;e de <a href=\"",$this->href(),"\">",$this->GetPageTag(),"</a> &agrave; ",$this->page["time"],".</div>";
		}


		// display page
		$this->RegisterInclusion($this->GetPageTag());
		echo $this->Format($this->page["body"], "wakka");
		$this->UnregisterLastInclusion();

		// if this is an old revision, display some buttons
		if (($this->page["latest"] == "N") && $this->HasAccess("write"))
		{
			$latest = $this->LoadPage($this->tag);
			?>
			<br />
			<?php echo  $this->FormOpen("edit") ?>
			<input type="hidden" name="previous" value="<?php echo  $latest["id"] ?>" />
			<input type="hidden" name="body" value="<?php echo  htmlspecialchars($this->page["body"]) ?>" />
			<input type="submit" value="R&eacute;&eacute;diter cette version archiv&eacute;e" />
			<?php echo  $this->FormClose(); ?>
			<?php
		}
	}
}
else
{
	echo "<i>Vous n'&ecirc;tes pas autoris&eacute; &agrave; lire cette page</i>" ;
}
?>
<hr class="hr_clear" />
</div>


<?php
$pageouverte = $this->GetTripleValue($this->GetPageTag(),'http://outils-reseaux.org/_vocabulary/comments', '', '');
if ((COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte!='0' ) || (!COMMENTAIRES_OUVERTS_PAR_DEFAUT && $pageouverte=='1')) {
	if ($HasAccessRead && (!$this->page || !$this->page["comment_on"]))
	{
		// load comments for this page
		$comments = $this->LoadComments($this->tag);
		
		// store comments display in session
		$tag = $this->GetPageTag();
		if (!isset($_SESSION["show_comments"][$tag]))
			$_SESSION["show_comments"][$tag] = ($this->UserWantsComments() ? "1" : "0");
		if (isset($_REQUEST["show_comments"])){	
		switch($_REQUEST["show_comments"])
		{
		case "0":
			$_SESSION["show_comments"][$tag] = 0;
			break;
		case "1":
			$_SESSION["show_comments"][$tag] = 1;
			break;
		}
		}
		// display comments!
		include_once('tools/tags/libs/tags.functions.php');
		$gestioncommentaire = '<strong class="lien_commenter">Commentaires sur cette page.'."\n";
		if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
		{
			$gestioncommentaire .= '<a href="'.$this->href('closecomments').'" title="D&eacute;sactiver les commentaires sur cette page">D&eacute;sactiver les commentaires</a>'."\n";
		}
		$gestioncommentaire .= '.</strong>'."\n";
		$gestioncommentaire .= "<div class=\"commentaires_billet_microblog\">\n";
		$gestioncommentaire .= afficher_commentaires_recursif($this->getPageTag(), $this);
		$gestioncommentaire .= "</div>\n";
		echo $gestioncommentaire;
	
	}
}
else //commentaire pas ouverts
{
	if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
	{
		echo '<strong class="admin_commenter">Commentaires d&eacute;sactiv&eacute;s '."\n".'<a href="'.$this->href('opencomments').'" title="Activer les commentaires sur cette page">Activer les commentaires</a></strong>.'."\n";
	}
}
$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();

?>
