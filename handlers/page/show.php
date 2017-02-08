<?php
/*
$Id: show.php 833 2007-08-10 01:16:57Z gandon $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2003  Eric DELORD
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDR?
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

// V?rification de s?curit?
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
  unset($_SESSION['redirects'][count($trace) - 1]);
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
			<input type="hidden" name="body" value="<?php echo  htmlspecialchars($this->page["body"], ENT_COMPAT, YW_CHARSET) ?>" />
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
	if ($this->page && $_SESSION["show_comments"][$tag])
	{
		// display comments header
		?>
		<div class="commentsheader">
			Commentaires [<a href="<?php echo  $this->href("", "", "show_comments=0") ?>">Cacher commentaires/formulaire</a>]
		</div>
		<?php

		// display comments themselves
		if ($comments)
		{
			foreach ($comments as $comment)
			{
				echo "<a name=\"",$comment["tag"],"\"></a>\n" ;
				echo "<div class=\"comment\">\n" ;
				if ($this->HasAccess('write', $comment['tag'])
				 || $this->UserIsOwner($comment['tag'])
				 || $this->UserIsAdmin($comment['tag']))
				{
					echo '<div class="commenteditlink">';
					if ($this->HasAccess('write', $comment['tag']))
					{
						echo '<a href="',$this->href('edit',$comment['tag']),'">&Eacute;diter ce commentaire</a>';
					}
					if ($this->UserIsOwner($comment['tag'])
					 || $this->UserIsAdmin())
					{
						echo '<br />','<a href="',$this->href('deletepage',$comment['tag']),'">Supprimer ce commentaire</a>';
					}
					echo "</div>\n";
				}
				echo $this->Format($comment["body"]),"\n" ;
				echo "<div class=\"commentinfo\">\n-- ",$this->Format($comment["user"])," (".$comment["time"],")\n</div>\n" ;
				echo "</div>\n" ;
			}
		}

		// display comment form
		echo "<div class=\"commentform\">\n" ;
		if ($this->HasAccess("comment"))
		{
			?>
				Ajouter un commentaire &agrave; cette page:<br />
				<?php echo  $this->FormOpen("addcomment"); ?>
					<textarea name="body" rows="6" cols="65" style="width: 100%"></textarea><br />
					<input type="submit" value="Ajouter Commentaire" accesskey="s" />
				<?php echo  $this->FormClose(); ?>
			<?php
		}
		echo "</div>\n" ;
	}
	else
	{
		?>
		<div class="commentsheader">
		<?php
			switch (count($comments))
			{
			case 0:
				echo "Il n'y a pas de commentaire sur cette page." ;
				break;
			case 1:
				echo "Il y a un commentaire sur cette page." ;
				break;
			default:
				echo "Il y a ",count($comments)," commentaires sur cette page." ;
			}
		?>

		[<a href="<?php echo  $this->href("", "", "show_comments=1") ?>">Afficher commentaires/formulaire</a>]

		</div>
		<?php
	}
}

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();

?>
