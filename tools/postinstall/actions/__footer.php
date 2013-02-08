<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

if (!$this->LoadPage('DerniersChangementsRSS')) {

	echo '<div class="info_box">'."\n".'<strong>Post-installation de pages</strong>'."\n";
	
	//insertion des pages de YesWiki
	$d = dir("tools/postinstall/setup/yeswiki/");
	
	// On prend le premier admin venu pour le mettre comme propri?taire des pages
	$result = explode("\n",$this->GetGroupACL('admins'));
	$admin_name = $result[0];

	$pages_ajoutees = ''; $pages_deja_existantes = '';
	while ($doc = $d->read()){
		if (is_dir($doc) || substr($doc, -4) != '.txt')
			continue;
		$pagecontent = implode ('', file("tools/postinstall/setup/yeswiki/$doc"));
		$pagename = trim(substr($doc,0,strpos($doc,'.txt')));
		
		// On ajoute toutes les pages du dossier, sauf celles deja existantes
		if  (!$this->LoadPage($pagename)) {		
			$sql = "Insert into ".$this->config["table_prefix"]."pages ".
				"set tag = '$pagename', ".
				"body = '".mysql_real_escape_string($pagecontent)."', ".
				"user = '" . mysql_real_escape_string($admin_name) . "', ".
				"owner = '" . mysql_real_escape_string($admin_name) . "', " .
				"time = now(), ".
				"latest = 'Y'";

			$this->Query($sql);

			// update table_links 
			$this->SetPage($this->LoadPage($pagename,"",0));
			$this->ClearLinkTable();
			$this->StartLinkTracking();
			$this->TrackLinkTo($pagename);
			$dummy = $this->Header();
			$dummy .= $this->Format($pagecontent);
			$dummy .= $this->Footer();
			$this->StopLinkTracking();
			$this->WriteLinkTable();
			$this->ClearLinkTable();
			if ($pages_ajoutees == '') {
				$pages_ajoutees .= $pagename;
			} else {
				$pages_ajoutees .= ', '.$pagename;
			}
		}
		
		else {
			if ($pages_deja_existantes == '') {
				$pages_deja_existantes = $pagename;
			} else {
				$pages_deja_existantes = $pages_deja_existantes.', '.$pagename;
			}
		}	

	}
	
	if ($pages_ajoutees != '') {
			echo $this->Format("\n".'Les pages suivantes ont ?t? cr??es : '."\n".$pages_ajoutees."\n");
	}
						
	if ($pages_deja_existantes != '') {
			echo $this->Format("\n".'Les pages suivantes n\'ont pas ?t? cr??es car elles existent d?j? : '."\n".$pages_deja_existantes);
	}
	
	echo '</div>'."\n";
}
?>
