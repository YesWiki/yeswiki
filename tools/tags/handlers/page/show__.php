<?php
/*
$Id: show__.php,v 1.1 2011-12-19 09:51:10 mrflos Exp $
Copyright (c) 2002, Florian Schmitt <florian@outils-reseaux.org>
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

// Verification de securite
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}

// on supprime la vieille gestion des commentaires
$string = '/\<div class="commentsheader"\>.*\<\/div\>/Uis';
$plugin_output_new = preg_replace($string, '', $plugin_output_new);

$output = '';

if ($GLOBALS["open_comments"][$tag]) {
	if ($HasAccessRead && (!$this->page || !$this->page["comment_on"]))
	{
		// load comments for this page
		$comments = $this->LoadComments($this->tag);
		
		// store comments display in session
		$tag = $this->GetPageTag();

		// display comments!
		include_once('tools/tags/libs/tags.functions.php');
		$gestioncommentaire = '<div id="yeswiki-comments-'.$tag.'" class="yeswiki-page-comments accordion hide">
	<div class="accordion-group">
		<div class="accordion-heading">';
		if (($this->UserIsOwner()) || ($this->UserIsAdmin())) {
			$gestioncommentaire .= '<a class="btn btn-danger pull-right" href="'.$this->href('closecomments').'" title="'._t('TAGS_DESACTIVATE_COMMENTS_ON_THIS_PAGE').'">'._t('TAGS_DESACTIVATE_COMMENTS').'</a>'."\n";
		}

		$gestioncommentaire .= '<a class="accordion-toggle comment-title" href="#comments-list-'.$tag.'" data-parent="#yeswiki-comments-'.$tag.'" data-toggle="collapse"><i class="icon-comment"></i>&nbsp;'._t('TAGS_COMMENTS_ON_THIS_PAGE').'</a>'."\n".'<div class="clearfix"></div>'."\n".
			'</div>
		<div class="accordion-body collapse in comments-list" id="comments-list-'.$tag.'">
		    <div class="accordion-inner">'."\n";
		$gestioncommentaire .= '<input type="hidden" id="initialpage" class="initial-page" value="'.$tag.'">'."\n";
		$gestioncommentaire .= afficher_commentaires_recursif($this->getPageTag(), $this);
		$gestioncommentaire .= "</div>\n</div>\n</div>\n</div>\n";
		$output .= $gestioncommentaire;
	
	}
}
else //commentaire pas ouverts
{
	if (($this->UserIsOwner()) || ($this->UserIsAdmin()))
	{
		//TODO: le rajouter aux droits acls wiki plutot que les afficher ici
		$output .= '<div class="well well-small hide"><i class="icon-comment"></i>&nbsp;'._t('TAGS_COMMENTS_DESACTIVATED').' '."\n".'<a class="btn btn-success pull-right" href="'.$this->href('opencomments').'" title="'._t('TAGS_ACTIVATE_COMMENTS_ON_THIS_PAGE').'">'._t('TAGS_ACTIVATE_COMMENTS').'</a><div class="clearfix"></div></div>'."\n";
	}
}

// on affiche la liste des mots cles disponibles pour cette page 
if (!CACHER_MOTS_CLES && (!isset($type) || !(isset($type) && $type == 'fiche_bazar')))
{
	$tabtagsexistants = $this->GetAllTags($this->GetPageTag());
	$tagspage = array();
	foreach ($tabtagsexistants as $tab)
	{
		$tagspage[] = _convert($tab["value"], 'ISO-8859-1');
	}
	if (count($tagspage)>0)
	{
		sort($tagspage);
		$tagsexistants = '';
		foreach ($tagspage as $tag)
		{
			$tag = stripslashes($tag);
			$tagsexistants .= '&nbsp;<a class="tag-label label label-info" href="'.$this->href('listpages',$this->GetPageTag(),'tags='.urlencode($tag)).'" title="'._t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS').'">'.$tag.'</a>';
		}
		$output .= '<i class="icon icon-tags"></i>'."\n".$tagsexistants."\n";
	}
}

$plugin_output_new = str_replace('	<script src="tools/tags/libs/tag.js"></script>'."\n", '', $plugin_output_new);
$plugin_output_new = str_replace('</body>', '	<script src="tools/tags/libs/tag.js"></script>'."\n".'</body>', $plugin_output_new);

$plugin_output_new = preg_replace ('/\<hr class=\"hr_clear\" \/\>/', '<hr class="hr_clear" />'."\n".$output, $plugin_output_new);

?>
