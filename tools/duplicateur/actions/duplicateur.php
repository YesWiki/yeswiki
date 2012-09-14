<?php
/*
textsearch.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002  Patrick PAUL
Copyright 2004  Jean Christophe ANDR�
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


/*
 * {{duplicateur source="Page1, Page2, Page3" target="prefixe1, prefixe2, prefixe3"}}
 * 
 * Demmarage  par bouton. Reserve  à l'administrateur.
 * 
 * 
 * 
 */

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


$sources = $this->GetParameter('source');
$targets = $this->GetParameter('target');

if ($sources) {
	$sources = preg_split('/[ ;,\|]/', addslashes($sources), -1, PREG_SPLIT_NO_EMPTY);
}
else {
    $sources = array();
}

if ($targets) {
	$targets = preg_split('/[ ;,\|]/', addslashes($targets), -1, PREG_SPLIT_NO_EMPTY);
}
else {
    $targets = array();
}


$res  = $this->FormOpen();
$res .= '<br />&nbsp;<b>Source(s)</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Cible(s)</b>  <br />';
$res .= '<br />';
$res .= '<input type="hidden" name="duplication" value="duplication" />';
$res .= '<textarea name="sources" rows="12" cols="20">' . 
implode("\n",$sources) . 
'</textarea>';
$res .= '<textarea name="targets" rows="12" cols="20">' . 
implode("\n",$targets) . 
'</textarea><br />';
$res .= '<input type="submit" value="Dupliquer" style="width: 120px" accesskey="d" />';
echo $res . $this->FormClose();

//print_r($_POST);
//print_r($sources);
//print_r($targets);

// Lancement, on tient compte des valeurs saisies manuellement

if ($_POST['duplication']=='duplication') {

	if ($this->UserIsAdmin()) {
		
	
		if ($_POST['sources']) {
			$_POST['sources'] = str_replace("\r", '', $_POST['sources']);
			$sources = preg_split('/[\n]/', addslashes($_POST['sources']), -1, PREG_SPLIT_NO_EMPTY);
		}
		else {
		    $sources = array();
		}
		
		if ($_POST['targets']) {
			$_POST['targets'] = str_replace("\r", '', $_POST['targets']);
			$targets = preg_split('/[\n]/', addslashes($_POST['targets']), -1, PREG_SPLIT_NO_EMPTY);
		}
		else {
		    $targets = array();
		}
		
		$user = $this->GetUserName();
		
		foreach ($sources as $source) {
			
			// Pas de gestion d'acl, ni de proprietaire, ni de link tracking !
	
			$sql = "SELECT * FROM ".$this->config["table_prefix"]."pages"
				. " WHERE tag = '".mysql_real_escape_string($source)."' AND "
				.  "latest = 'Y' LIMIT 1";
			
			if ($page = $this->LoadSingle($sql)) {
	
				foreach ($targets as $target) {
					
					if ($target!=$this->config["table_prefix"]) {
						
						$body = $page ['body'];
						
						// set all other revisions to old
						$this->Query("update ".$target."pages set latest = 'N' where tag = '".mysql_real_escape_string($source)."'");
						
						
						// add new revision
						$this->Query("insert into ".$target."pages set ".
							"tag = '".mysql_real_escape_string($source)."', ".
							"comment_on = '', ".
							"time = now(), ".
							"owner = '', ".
							"user = '".mysql_real_escape_string($user)."', ".
							"latest = 'Y', ".
							"body = '".mysql_real_escape_string(chop($body))."'");
			
					}			
						
				}
			}
		}
	}	
	else {

		echo "Action r&eacute;serv&eacute;e aux Administrateurs";
			
	}
		
	
}
	
?>
