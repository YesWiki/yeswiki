<?php
define ('CHEMIN', 'tools'.DIRECTORY_SEPARATOR.'coverflow'.DIRECTORY_SEPARATOR.'actions'.DIRECTORY_SEPARATOR);

//Cas de la sauvegarde
if (isset($_GET['lien']) && isset($_GET['titre']) && isset($_GET['image'])) {
  if ($_POST['submit']=='Sauver') {  	
  	$spam=0;
  	preg_match_all("/[a-z]+:\/\/\S+/",$_POST['descriptif'],$matches);
	if (count($matches [0])>1) {
		$spam=1;
	}
	preg_match_all("/[a-z]+:\/\/\S+/",$_POST['titre'],$matches);
	if (count($matches [0])>1) {
		$spam=1;
	}
	
  	if (!$spam) {
	  	$chaine="\n~~\"\"<!--".$_GET['image_x']."-".$_GET['image_y']."-->\"\"[".$_POST['image_texte']."]~~";
	  	if ($_POST['couleur_xy']) {
		  	$chaine="\n~~\"\"<!--".$_GET['image_x']."-".$_GET['image_y']."-".$_POST['couleur_xy']."-->\"\"[".$_POST['image_texte']."]~~";
			preg_match('/([0-9][0-9]*)-([0-9][0-9]*)-*([a-zA-Z]*)/',$location,$elements);
	  	}
		$donneesbody = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where tag = '".mysql_real_escape_string($this->GetPageTag().'DonneesCoverflow')."'and latest = 'Y' limit 1");
	  	$this->SavePage($this->GetPageTag().'DonneesCoverflow',$donneesbody['body'].$chaine);
  		$this->Redirect($this->Href());
  	}
  }
  else {
  	$this->Redirect($this->Href());
  }
}

//init de res
		$res = '<div id="imageflow"> 
		<div id="loading">
			<b>Loading images</b><br/>
			<img src="'.CHEMIN.'presentations'.DIRECTORY_SEPARATOR.'loading.gif" width="208" height="13" alt="loading" />
		</div>
		<div id="images">'."\n";
//on recupere les donnees de la page XML associee
$xml = $this->LoadSingle("select * from ".$this->config["table_prefix"]."pages where tag = '".mysql_real_escape_string($this->GetPageTag().'DonneesCoverflow')."'and latest = 'Y' limit 1");
if ($xml!='') {
	$donnees = preg_replace ("/".'(\\{\\{template)'.'(.*?)'.'(\\}\\})'."/is", '', $xml['body']);
	$tab_ligne = explode("\n",$donnees);
	foreach( $tab_ligne as $ligne ) {
		list($titre, $lien, $image) = explode("|",$ligne);
		if ($titre!=NULL) $res .= '<img src="'.CHEMIN.'presentations'.DIRECTORY_SEPARATOR.'reflect.php?img='.trim($image).'" '.
								  'longdesc="'.trim($lien).'" '.
								  'alt="'.$titre.'" />'."\n";
	}
	$res .= '</div>
		<div id="captions"></div>
		<div id="scrollbar">
			<div id="slider"></div>
		</div>
	</div>';



//	require_once('tools'.DIRECTORY_SEPARATOR.'mooflow'.DIRECTORY_SEPARATOR.'libs'.DIRECTORY_SEPARATOR.'IsterXmlSimpleXMLImpl.php');
//	$impl = new IsterXmlSimpleXMLImpl;
//	$doc  = $impl->load_string($xml['body']);
//	foreach( $doc->donnees->children() as $enregistrement ) {
//		var_dump($enregistrement);
//	    $res .= $enregistrement->url->CDATA().'toto<br />';
//	    //print "\n";
//	}
//	//print '<br /><br />url:'.$doc->donnees->enregistrement[0]->url->CDATA().'<br /><br />';
echo $res;
} 

?>