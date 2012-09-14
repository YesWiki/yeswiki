<?

if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}


if (!function_exists("resolvesVariable")) {
	function resolvesVariable($variables, $template) {
		$keys = array();
		$values = array();
		$spam=0;
		
		foreach($variables as $key=>$value) {
			
			preg_match_all("/[a-z]+:\/\/\S+/",$value,$matches);
			if (count($matches [0])>1) {
				$spam=1;
			}
			
			$keys[] = '%'.$key.'%';
			$values[] = $value;
		}
		
		if (!$spam) {
			return str_replace($keys, $values, $template);
		}
		else return null;
	}
}

$use = $this->GetParameter("use", false);
$template = $this->GetParameter("template", false);

// Sauvegarde OU Affichage
if (isset($_POST['Form'])) {

	// resolution des variables
	$template = $this->LoadPage($template);
	$chaine = resolvesVariable($_POST, $template['body']);

	if ($chaine!=null) {
		if (preg_match("/~\*~(.*?)~\*~/ms",$this->page['body'])) {
			$this->page['body']= preg_replace("/~\*~(.*?)~\*~/ms", "~*~\\1\n".$chaine."\n~*~",$this->page['body']);
		  	$this->SavePage($this->getPageTag(),$this->page['body']);
		}
		else {
			$this->page['body']= $this->page['body']."\n".$chaine."\n";
		  	$this->SavePage($this->getPageTag(),$this->page['body']);
		}
		
	}
	$this->Redirect($this->Href());	
	exit;
}

else {
	// affichage du formulaire
	$completion=0;			
	if (($use !== false) && ($template !== false)) {
		$page = $this->LoadPage($use);
		
		echo $this->FormOpen();
		
		echo '<input type="hidden" name="Form" value="true" />'."\n";
		
		
		$sortieform = $this->Format($page["body"], "wakka");
		
		if (preg_match('/completion_commune/',$sortieform)) {
			$sortieform=preg_replace('/completion_commune"/','text" autocomplete="off"',$sortieform); 
			$completion=1;
			
		}
		echo $sortieform;
		
		echo '<input type="submit" value="Enregistrer" />'."\n";
		//echo '<a href="'.$this->Href("mapview")."&template=".$template.'">Exporter</a>'."\n";
		
		echo $this->FormClose();
		
		if ($completion) {
		
			echo "<script language=\"JavaScript\" type=\"text/javascript\" src=\"tools/cartowiki/bib/XmlHttpLookup.js\"></script>";
			
			echo "<script type=\"text/javascript\"language=\"JavaScript\">";
			echo "\n";
			echo "<!--"; 
			echo "\n";
			echo "InitQueryCode('commune',"; 
			echo "'";
			echo $this->Href();
			echo "/locationAssistant&lieu=');";
			echo "\n";
			echo "//--> ";
			echo "\n";
			echo "</script>";
			
		
			
		}

	}
	
}

?>
