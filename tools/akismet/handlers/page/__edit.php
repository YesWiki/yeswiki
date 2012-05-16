<?php


if (!defined('WIKINI_VERSION')) {
	die ('acc&egrave;s direct interdit');
}


if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	// preview?
	if ($_POST["submit"] == "Sauver")
	{
		if (empty($_POST['body'])){
			$body = $this->page['body'];
		} else {
			$body = $_POST['body'];
		}
		
		$body = str_replace("\r", "", $_POST["body"]);
		
		include 'tools/akismet/akismet.class.php'; 
		include 'tools/akismet/akismet.key.php'; 
		
		// load array with comment data
		
		 $userinfo=$this->LoadSingle("select email from ".$this->config["table_prefix"]."users where name = '".mysql_escape_string($this->GetUserName())."'");
		 $a = parse_url($this->config['base_url']);
		 $website = ($a['scheme'].'://'.$a['host'].dirname($a['path']));
		
		$bodyA = explode("\n", $body);
		$bodyB = explode("\n", $this->page["body"]);     
		$added = array_diff($bodyA, $bodyB);

		// Pre-check :
		
		// No UTF8 :
		$text_added=implode("\n", $added);
		if (preg_match('/Ã/',$text_added)) {
			$this->SetMessage("Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car le contenu ajouté est considéré comme spam");
			$this->Redirect($this->href());
		}
	
		 $comment = array ( 'author' => $this->GetUserName(),
		 					'email' => $userinfo['email'],
		 					'website' => $website, 
							'body' => $text_added, 
							'permalink' => $this->config['base_url'].$this->page['tag'], 
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
				$this->SetMessage("Cette page n\'a pas &eacute;t&eacute; enregistr&eacute;e car le contenu ajouté est considéré comme spam");
				$this->Redirect($this->href());
			} 
		}
	}

}