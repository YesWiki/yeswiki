<?php
/*
$Id: ajaxaddcomment.php,v 1.4 2011-12-19 09:51:10 mrflos Exp $
Copyright (c) 2009, Florian Schmitt <florian@outils-reseaux.org>
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

// on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) 
{
	// on initialise la sortie:
	header('Content-type:application/json');

	if ($this->page && $this->HasAccess("comment", $_POST["initialpage"]) && isset($_POST['antispam']) && $_POST['antispam']==1)
	{
		// find number
		$sql = 'SELECT MAX(SUBSTRING(tag, 8) + 0) AS comment_id'
			. ' FROM ' . $this->GetConfigValue('table_prefix') . 'pages'
			. ' WHERE comment_on != ""';
		if ($lastComment = $this->LoadSingle($sql))
		{
			$num = $lastComment['comment_id'] + 1;
		}
		else
		{
			$num = "1";
		}
	
		$body = utf8_decode(trim($_POST["body"]));
		if ($body)	
		{
			// store new comment
			$wakkaname = "Comment".$num;
			$this->SavePage($wakkaname, $body, $this->tag, true);
			
			$comment = $this->LoadPage($wakkaname);
			$valcomment['commentaires'][0]['tag'] = $comment["tag"];
			$valcomment['commentaires'][0]['body'] = $this->Format($comment["body"]);
			$valcomment['commentaires'][0]['infos'] = $this->Format($comment["user"]).", ".date(_t('TAGS_DATE_FORMAT'), strtotime($comment["time"]));
			$valcomment['commentaires'][0]['hasrighttoaddcomment'] = $this->HasAccess("comment", $_POST['initialpage']);
			$valcomment['commentaires'][0]['hasrighttomodifycomment'] = $this->HasAccess('write', $comment['tag']) || $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
			$valcomment['commentaires'][0]['hasrighttodeletecomment'] = $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin();
			$valcomment['commentaires'][0]['replies'] = '';
			include_once('tools/libs/squelettephp.class.php');
			$squelcomment = new SquelettePhp('tools/tags/presentation/templates/comment_list.tpl.html');
			$squelcomment->set($valcomment);
			echo $_GET['jsonp_callback']."(".json_encode(array("html"=>utf8_encode($squelcomment->analyser()))).")";
		}
	}
}
?>
