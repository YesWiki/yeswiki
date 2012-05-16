<?php
/*
addcomment.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright  2003  Eric FELDSTEIN
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

//vérification de sécurité
if (!eregi("wakka.php", $_SERVER['PHP_SELF'])) {
    die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("comment"))
{
	$body = trim($_POST["body"]);
	if (!$body)
	{
		$this->SetMessage("Commentaire vide  -- pas de sauvegarde !");
	}
	else
	{
	
		include 'tools/akismet/akismet.class.php'; 
		include 'tools/akismet/akismet.key.php'; 
		
		// load array with comment data
		
		 $userinfo=$this->LoadSingle("select email from ".$this->config["table_prefix"]."users where name = '".mysql_escape_string($this->GetUserName())."'");
		 $a = parse_url($this->config['base_url']);
		 $website = ($a['scheme'].'://'.$a['host'].dirname($a['path']));
		
	
		 $comment = array ( 'author' => $this->GetUserName(),
		 					'email' => $userinfo['email'],
		 					'website' => $website, 
							'body' => $body, 
							'permalink' => $this->config['base_url'].$this->tag, 
							'user_ip' => $_SERVER['REMOTE_ADDR'], 
							 'user_agent' => $_SERVER['HTTP_USER_AGENT']
							 ); 
	
		// instantiate an instance of the class
		$akismet = new Akismet($website, $akismet_key , $comment);
		// test for errors
		if($akismet->errorsExist()) { // returns true if any errors exist
		  // do nothing ...
		}
		else {
			// No errors, check for spam
			if ($akismet->isSpam()) { // returns true if Akismet thinks the comment is spam
				$this->SetMessage("Ce commentaire n\'a pas &eacute;t&eacute; enregistr&eacute;e car le contenu ajouté est considéré comme spam");
				$this->Redirect($this->href());
			} 
		}
	}

}

?>