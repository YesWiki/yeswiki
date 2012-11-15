<?php
/*
$Id: ajaxedit.php,v 1.3 2010-01-27 15:19:41 mrflos Exp $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002, 2003 Patrick PAUL
Copyright 2003  Eric FELDSTEIN
Copyright 2004  Jean Christophe ANDRÃ‰
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
if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}
//on ne fait quelque chose uniquement dans le cas d'une requete jsonp
if (isset($_GET['jsonp_callback'])) 
{
	// on initialise la sortie:
	header('Content-type:application/json');
	$output = '';
	
	if ($this->HasAccess('write') && $this->HasAccess('read')) {
		if (!empty($_GET['submit'])){
			$submit = $_GET['submit'];
		} else {
			$submit = false;
		}
		
		// fetch fields
		if (empty($_GET['previous'])){
			$previous = $this->page['id'];
		} else {
			$previous = $_GET['previous'];
		}
		if (empty($_GET['body'])){
			$body = $this->page['body'];
		} else {
			$body =$_GET['body'];
		}
	
	
		switch ($submit){
			case 'Aperçu':
				$temp = $this->SetInclusions(); // a priori, ca ne sert à rien, mais on ne sait jamais...
				$this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
				$output .=
					"<div class=\"page_preview\">\n".
					"<div class=\"prev_alert\"><strong>Aper&ccedil;u</strong></div>\n".
					$this->Format($body)."\n\n".
					$this->FormOpen("edit").
					"<input type=\"hidden\" name=\"previous\" value=\"$previous\" />\n".
					"<input type=\"hidden\" name=\"body\" value=\"".htmlspecialchars($body)."\" />\n".
					"<br />\n".
					"<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" />\n".
					"<input name=\"submit\" type=\"submit\" value=\"R&eacute;&eacute;diter\" accesskey=\"p\" />\n".
					"<input type=\"button\" value=\"Annulation\" onclick=\"document.location='" . addslashes($this->href()) . "';\" />\n".
					$this->FormClose()."\n"."</div>\n";
				$this->SetInclusions($temp);
				break;
				
			// pour les navigateurs n'interprétant pas le javascript
			case 'Annulation':
				$this->Redirect($this->Href());
				exit; // sécurité
	
			// only if saving:
			case 'Sauver':
				// check for overwriting
				if ($this->page && $this->page['id'] != $_GET['previous'])	{
					$error = 'ALERTE : '.
					"Cette page a &eacute;t&eacute; modifi&eacute;e par quelqu'un d'autre pendant que vous l'&eacute;ditiez.<br />\n".
					"Veuillez copier vos changements et r&eacute;&eacute;diter cette page.\n";
				} else { // store
					$body = str_replace("\r", '', utf8_decode($body));
					
					// teste si la nouvelle page est differente de la précédente 
					if(rtrim($body)==rtrim($this->page["body"])) {
						echo $_GET['jsonp_callback']."(".json_encode(array("nochange"=>'1')).")";
					} else { // sécurité
						// add page (revisions)
						$this->SavePage($this->tag, $body);
		
						// now we render it internally so we can write the updated link table.
						$this->ClearLinkTable();
						$this->StartLinkTracking();
						$temp = $this->SetInclusions(); // a priori, ca ne sert à rien, mais on ne sait jamais...
						$this->RegisterInclusion($this->GetPageTag()); // on simule totalement un affichage normal
						$this->Format($body);
						$this->SetInclusions($temp);
						if($user = $this->GetUser()) {
							$this->TrackLinkTo($user['name']);
						}
						if($owner = $this->GetPageOwner()) {
							$this->TrackLinkTo($owner);
						}
						$this->StopLinkTracking();
						$this->WriteLinkTable();
						$this->ClearLinkTable();
		
						// retour HTML pour ajax
						if (isset($_GET["commentaire"]) && $_GET["commentaire"]==1)
	 					{
							$comment = $this->LoadPage($this->tag);
		 						
							$valcomment['commentaires'][0]['tag'] = $comment["tag"];
							$valcomment['commentaires'][0]['body'] = $this->Format($comment["body"]);
							$valcomment['commentaires'][0]['infos'] = "de ".$this->Format($comment["user"]).", ".date("\l\e d.m.Y &\a\g\\r\av\e; H:i:s", strtotime($comment["time"]));
							$valcomment['commentaires'][0]['actions'] = '<a href="'.$this->href('', $comment['tag']).'" class="repondre_commentaire">R&eacute;pondre</a> ';
							if ($this->HasAccess('write', $comment['tag']) || $this->UserIsOwner($comment['tag']) || $this->UserIsAdmin($comment['tag']))
							{
								$valcomment['commentaires'][0]['actions'] .= '<a href="'.$this->href('edit', $comment['tag']).'" class="editer_commentaire">Editer</a> ';
							}			
							if ($this->UserIsOwner($comment['tag']) || $this->UserIsAdmin())
							{
								$valcomment['commentaires'][0]['actions'] .= '<a href="'.$this->href('deletepage', $comment['tag']).'" class="supprimer_commentaire">Supprimer</a>'."\n" ;
							}									
							include_once('tools/tags/lib/squelettephp.class.php');
							$squelcomment = new SquelettePhp('tools/tags/presentation/commentaire_microblog.tpl.html');
							$squelcomment->set($valcomment);
							echo $_GET['jsonp_callback']."(".json_encode(array("html"=>utf8_encode($squelcomment->analyser()))).")";							
						}
						else {
							echo $this->format($body);
						}
					}
					
					// sécurité
					exit;
				}
				// NB.: en cas d'erreur on arrive ici, donc default sera exécuté...
			default:
				// display form
				if (isset($error)) {
					$output .= "<div class=\"error\">$error</div>\n";
				}
				
				// append a comment?
				if (isset($_REQUEST['appendcomment'])) {
					$body = trim($body);
				}
				
				$output .=
					$this->FormOpen('ajaxedit').
					"<input type=\"hidden\" name=\"previous\" value=\"$previous\" />\n".
					"<textarea id=\"body\" name=\"body\" wrap=\"soft\" class=\"commentaire_microblog\">\n".
					htmlspecialchars($body).
					"</textarea><br />\n".
					($this->config['preview_before_save'] ? '' : "<input name=\"submit\" type=\"button\" class=\"bouton_submit\" value=\"Sauver\" accesskey=\"s\" />\n").
					(isset($_GET["commentaire"]) && $_GET["commentaire"]==1 ? '' : "<input name=\"submit\" type=\"button\" value=\"Aper&ccedil;u\" class=\"bouton_preview\" accesskey=\"p\" />\n").
					"<input type=\"button\" value=\"Annulation\" class=\"bouton_annul\" />\n".
					$this->FormClose();
		} // switch
	} else {
		$output .= "<i>Vous n'avez pas acc&egrave;s en &eacute;criture &agrave; cette page !</i>\n";
	}
	$response = $_GET['jsonp_callback']."(".json_encode(array("html"=>utf8_encode($output))).")";
	echo $response;
}
?>
