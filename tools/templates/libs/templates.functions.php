<?php
if (!defined("WIKINI_VERSION")) {
            die ("acc&egrave;s direct interdit");
}

/**
 * 
 * Parcours des dossiers a la recherche de templates
 * 
 * @param $directory : chemin relatif vers le dossier contenant les templates
 * 
 * return array : tableau des themes trouves, ranges par ordre alphabetique
 * 
 */
function search_template_files($directory) {
	$tab_themes = array();
	
	$dir = opendir($directory);
	while (false !== ($file = readdir($dir))) {    	
		if  ($file!='.' && $file!='..' && $file!='CVS' && is_dir($directory.DIRECTORY_SEPARATOR.$file)) {
			$dir2 = opendir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'styles');
		    while (false !== ($file2 = readdir($dir2))) {
		    	if (substr($file2, -4, 4)=='.css' || substr($file2, -5, 5)=='.less') $tab_themes[$file]["style"][$file2] = $file2;
		    }
		    closedir($dir2);
		    if (is_array($tab_themes[$file]["style"])) ksort($tab_themes[$file]["style"]);
		    $dir3 = opendir($directory.DIRECTORY_SEPARATOR.$file.DIRECTORY_SEPARATOR.'squelettes');
		    while (false !== ($file3 = readdir($dir3))) {
		    	if (substr($file3, -9, 9)=='.tpl.html') $tab_themes[$file]["squelette"][$file3]=$file3;	    
		    }	    	
		    closedir($dir3);
		    if (is_array($tab_themes[$file]["squelette"])) ksort($tab_themes[$file]["squelette"]);
	    }
	}
	closedir($dir);
	
	if (is_array($tab_themes)) ksort($tab_themes);
	
	return $tab_themes;
}


//remplace juste la premiere occurence d'une chaine de caracteres
function str_replace_once($from, $to, $str) {
    if(!$newStr = strstr($str, $from)) {
        return $str;
    }
    $iNewStrLength = strlen($newStr);
    $iFirstPartlength = strlen($str) - $iNewStrLength;
    return substr($str, 0, $iFirstPartlength).$to.substr($newStr, strlen($from), $iNewStrLength);
} 

// str_ireplace est php5 seulement 
if (!function_exists('str_ireplacement')) { 
  function str_ireplacement($search,$replace,$subject){
    $token = chr(1);
    $haystack = strtolower($subject);
    $needle = strtolower($search);
    while (($pos=strpos($haystack,$needle))!==FALSE){
      $subject = substr_replace($subject,$token,$pos,strlen($search));
      $haystack = substr_replace($haystack,$token,$pos,strlen($search));
    }
    $subject = str_replace($token,$replace,$subject);
    return $subject;
  }
}

//fonction recursive pour detecter un nomwiki deja present 
function nomwikidouble($nomwiki, $nomswiki) {
	if (in_array($nomwiki, $nomswiki)) {
		return nomwikidouble($nomwiki.'bis', $nomswiki);
	} else {
		return $nomwiki;
	}
}

//fonction pour remplacer les liens vers les NomWikis n'existant pas
function replace_missingpage_links($output) {	
	$pattern = '/<span class="missingpage">(.*)<\/span><a href="'.str_replace(array('/','?'), array('\/','\?'), 
				$GLOBALS['wiki']->config['base_url']).'(.*)\/edit">\?<\/a>/U';
	preg_match_all($pattern, $output, $matches, PREG_SET_ORDER);

	foreach ($matches as $values) {
		// on passe en parametres GET les valeurs du template de la page de provenance, pour avoir le même graphisme dans la page créée
		$query_string = 'theme='.urlencode($GLOBALS['wiki']->config['favorite_theme']).
						'&amp;squelette='.urlencode($GLOBALS['wiki']->config['favorite_squelette']).
						'&amp;style='.urlencode($GLOBALS['wiki']->config['favorite_style']).
						((!$GLOBALS['wiki']->IsWikiName($values[1])) ? '&amp;body='.urlencode($values[1]) : '');
		$replacement = '<a class="yeswiki-editable" href="'.$GLOBALS['wiki']->href("edit", $values[2], $query_string).'">'.
						$values[1].'&nbsp;<img src="tools/templates/presentation/images/crayon.png" alt="crayon" /></a>';
		$output = str_replace_once( $values[0], $replacement, $output );
	}
	return $output;
}

/**
 * 
 * crée un diaporama à partir d'une PageWiki
 * 
 * @param $pagetag : nom de la PageWiki
 * @param $template : fichier template pour le diaporama
 * @param $class : classe CSS à ajouter au diaporama
 * 
 */
function print_diaporama($pagetag, $template = 'diaporama_slide.tpl.html', $class = '') {
	// On teste si l'utilisateur peut lire la page
	if (!$GLOBALS['wiki']->HasAccess("read", $pagetag))
	{
		return '<div class="error_box">Vous n\'avez pas le droit d\'acc&eacute;der &agrave; cette page.</div>'. $GLOBALS['wiki']->Format('{{login template="minimal.tpl.html"}}');
	}
	else
	{
		// On teste si la page existe
		if (!$page = $GLOBALS['wiki']->LoadPage($pagetag))
		{
			return '<div class="error_box">Page '.$pagetag.' non existante.</div>';
		}
		else
		{
			$body_f = $GLOBALS['wiki']->format($page["body"]);
			$body = preg_split('/(.*<h2>.*<\/h2>)/',$body_f,-1,PREG_SPLIT_DELIM_CAPTURE);      
	
			if (!$body)
			{
				return '<div class="=error_box">La page '.$pagetag.' ne peut pas &ecirc;tre d&eacute;coup&eacute;e en diapositives.</div>';
			}
			else
			{			
				// préparation des tableaux pour le squelette -------------------------
				$i = 0 ;
				$slides = array() ;
				$titles = array() ;
				foreach($body as $slide)
				{
					//pour les titres de niveau 2, on les transforme en titre 1
					if (preg_match('/^<h2>.*<\/h2>/', $slide)) 
					{
						$i++;
						$titles[$i] = str_replace('h2', 'h1', $slide);
					}
					//sinon, on affiche
					else 
					{
						//s'il y a un titre de niveau 1 qui commence la diapositive, on la déplace en titre (sert surtout pour la première page)
						if (preg_match('/^<h1>.*<\/h1>/', $slide)) 
						{
							$split = preg_split('/(.*<h1>.*<\/h1>)/',$slide, -1, PREG_SPLIT_DELIM_CAPTURE);
							$titles[$i] = $split[1];
							$slide = $split[2];
						}
						$html_slide = '' ;
						if (isset($titles[$i]) && $titles[$i] != "") { 
							$html_slide .= "<div class=\"slide-header\">".$titles[$i]."</div>\n" ;
							$titles[$i] = strip_tags($titles[$i]) ;
						}
						$html_slide .= $slide ;
						$slides[] = $html_slide ;
					}
				}
			}
		}
		
		$buttons = '';
		//si la fonction est appelée par le handler diaporama, on ajoute les liens d'édition et de retour
		if ($GLOBALS['wiki']->GetMethod() == "diaporama") {
			$buttons .= '<div class="buttons-action"><a class="button-edit" href="'.$GLOBALS['wiki']->href('edit',$pagetag).'">&Eacute;diter</a>'."\n";
			$buttons .= '<a class="button-quit" href="'.$GLOBALS['wiki']->href('',$pagetag).'">Quitter</a></div>'."\n";
		}
		
		//on affiche le template
		if (!class_exists('SquelettePhp')) include_once('tools/templates/libs/squelettephp.class.php');
		$squel = new SquelettePhp('tools/templates/presentation/templates/'.$template);
		$squel->set(array(
			"pagetag" => $pagetag,
			"slides" => $slides,
			"titles" => $titles,
			"buttons" => $buttons,
			"class" => $class
		));
		$output = $squel->analyser() ;
		
		//on prépare le javascript du diaporama, qui sera ajoutée par l'action footer de template, à la fin du html
		$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'<script> 
			$("#slide_show_'.$pagetag.'").scrollable({mousewheel:false}).navigator({history: true}).data("scrollable");
			$("#thumbs_'.$pagetag.' .navi a[title]").tooltip({position:	\'top center\', opacity:0.9, tipClass:\'tooltip-slideshow\', offset:[5, 0]});
			</script>'."\n";
		return $output;
	}
}

?>
