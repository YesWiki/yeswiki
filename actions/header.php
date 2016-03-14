<?php
/* header.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003, 2004, 2006 Charles NEPOTE
Copyright 2002  Patrick PAUL
Copyright 2003  Eric DELORD
Copyright 2004, 2006, 2007  Didier LOISEAU
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

$charset = 'iso-8859-1';

// Page info
$page_name = $this->GetPageTag();
$page_addr = $this->href();

header("Content-Type: text/html; charset=$charset");

// <head> : metas & style
if ($this->GetMethod() != 'show'
	|| !empty($_REQUEST['phrase'])
	|| !empty($_REQUEST['show_comments'])
	|| isset($_REQUEST['time']))
{
	$additionnal_metas = "<meta name=\"robots\" content=\"noindex, nofollow\" />\n";
}
else
{
	$additionnal_metas = '';
}
$meta_keywords = $this->GetConfigValue("meta_keywords");
$meta_description = $this->GetConfigValue("meta_description");
$imported_style = isset($_COOKIE["sitestyle"]) ? htmlspecialchars($_COOKIE["sitestyle"], ENT_COMPAT, YW_CHARSET) : 'wakka';

// Page contents
$body_attr = ($message = $this->GetMessage()) ? "onLoad=\"alert('".addslashes($message)."');\" " : "";
$wiki_name = $this->GetWakkaName();
$page_search = $this->href('', 'RechercheTexte', 'phrase=' . urlencode($page_name));
$root_page = $this->ComposeLinkToPage($this->config["root_page"]);
$navigation_links = $this->config["navigation_links"] ? $this->Format($this->config["navigation_links"]) : "";
$user_name = $this->Format($this->GetUserName());
$disconnect_link = $this->GetUser() ? '(<a href="' . $this->href('', 'ParametresUtilisateur', 'action=logout') . "\">D&eacute;connexion</a>)\n" : '';

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $charset ?>" />
		<meta name="keywords" content="<?php echo $meta_keywords ?>" />
		<meta name="description" content="<?php echo $meta_description ?>" />
		<?php echo $additionnal_metas ?>
		<title><?php echo "$wiki_name:$page_name" ?></title>
		<link rel="stylesheet" type="text/css" media="screen" href="wakka.basic.css" />
		<style type="text/css" media="all">
			<!--
			@import url(<?php echo $imported_style ?>.css);
			-->
		</style>
		<script type="text/javascript">
			<!--
			function fKeyDown(e) {
				if (e == null) e = event;
				if (e.keyCode == 9) {
					if (typeof(document["selection"]) != "undefined") {	// ie
						e.returnValue = false;
						document.selection.createRange().text = String.fromCharCode(9);
					} else if (typeof(this["setSelectionRange"]) != "undefined") {	// other
						var start = this.selectionStart;
						this.value = this.value.substring(0, start) + String.fromCharCode(9) + this.value.substring(this.selectionEnd);
						this.setSelectionRange(start + 1, start + 1);
						return false;
					}
				}
				return true;
			}
			function doubleClickEdit(e)
			{
				if (e == null) e = event;
				source = document.all ? e.srcElement : e.target;
				if( source.nodeName == "TEXTAREA" || source.nodeName == "INPUT") return;
				document.location = '<?php echo addslashes($this->Href('edit')) ?>';
			}
			/** invert all checkboxes that are descendant of a given parent */
			function invert_selection(parent_id)
			{
				items = document.getElementById(parent_id).getElementsByTagName('input');
				for (i = 0; i < items.length; i++)
				{
					item = items[i];
					if (item && item.type == 'checkbox')
					{
						item.checked = !item.checked;
					}
				}
				return false;
			}
			//-->
		</script>
	</head>


	<body <?php echo $body_attr ?> >

	<div style="display: none;">
		<a href="<?php echo $page_addr ?>/resetstyle" accesskey="7"></a>
	</div>

	<h1 class="wiki_name"><?php echo $wiki_name ?></h1>

	<h1 class="page_name">
		<a href="<?php echo $page_search ?>"><?php echo $page_name ?></a>
	</h1>

	<div class="header">
		<?php echo $root_page ?> ::
		<?php echo $navigation_links ?> ::
		Vous &ecirc;tes <?php echo $user_name ?> <?php echo $disconnect_link ?>
	</div>

