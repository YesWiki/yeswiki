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
	while ($dir && ($file = readdir($dir)) !== false) {    	
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



/**
*
* remplace juste la premiere occurence d'une chaine de caracteres
*
* @param $from : partie de la chaine recherch?e
* @param $to   : chaine de remplacement
* @param $str  : chaine entree
*
* return string : chaine entree avec la premiere occurence changee
*
*/
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


/**
*
* savoir si l'url est bien une image
*
* @param $url : url de l'image
*
* return boolean : indique si l'url est une image ou pas
*
*/
function image_exists($url) {
	$info = @getimagesize($url);
	return((bool) $info);
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
		// on passe en parametres GET les valeurs du template de la page de provenance, pour avoir le m?me graphisme dans la page creee
		$query_string = 'theme='.urlencode($GLOBALS['wiki']->config['favorite_theme']).
						'&amp;squelette='.urlencode($GLOBALS['wiki']->config['favorite_squelette']).
						'&amp;style='.urlencode($GLOBALS['wiki']->config['favorite_style']).
						'&amp;bgimg='.urlencode($GLOBALS['wiki']->config['favorite_background_image']).				
						((!$GLOBALS['wiki']->IsWikiName($values[1])) ? '&amp;body='.urlencode($values[1]) : '');
		$replacement = '<a class="yeswiki-editable" href="'.$GLOBALS['wiki']->href("edit", $values[2], $query_string).'"><i class="icon-pencil"></i>&nbsp;'.
						$values[1].'</a>';
		$output = str_replace_once( $values[0], $replacement, $output );
	}
	return $output;
}


/**
 * 
 * cree un diaporama a partir d'une PageWiki
 * 
 * @param $pagetag : nom de la PageWiki
 * @param $template : fichier template pour le diaporama
 * @param $class : classe CSS a ajouter au diaporama
 * 
 */
function print_diaporama($pagetag, $template = 'diaporama_slides.tpl.html', $class = '') {
	// On teste si l'utilisateur peut lire la page
	if (!$GLOBALS['wiki']->HasAccess("read", $pagetag))
	{
		return '<div class="alert alert-danger">'.TEMPLATE_NO_ACCESS_TO_PAGE.'</div>'. $GLOBALS['wiki']->Format('{{login template="minimal.tpl.html"}}');
	}
	else
	{
		// On teste si la page existe
		if (!$page = $GLOBALS['wiki']->LoadPage($pagetag))
		{
			return '<div class="alert alert-danger">'.TEMPLATE_PAGE_DOESNT_EXIST.' ('.$pagetag.').</div>';
		}
		else
		{
			$body_f = $GLOBALS['wiki']->format($page["body"]);
			$body = preg_split('/(.*<h2>.*<\/h2>)/',$body_f,-1,PREG_SPLIT_DELIM_CAPTURE);      
	
			if (!$body)
			{
				return '<div class="=alert alert-danger">'.TEMPLATE_PAGE_CANNOT_BE_SLIDESHOW.' ('.$pagetag.').</div>';
			}
			else
			{			
				// preparation des tableaux pour le squelette -------------------------
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
						//s'il y a un titre de niveau 1 qui commence la diapositive, on la deplace en titre (sert surtout pour la premiere page)
						if (preg_match('/^<h1>.*<\/h1>/', $slide)) 
						{
							$split = preg_split('/(.*<h1>.*<\/h1>)/',$slide, -1, PREG_SPLIT_DELIM_CAPTURE);
							$titles[$i] = $split[1];
							$slide = $split[2];
						}
						//$html_slide = '' ;
						//if ($titles[$i] != "") { 
							//$html_slide .= "<div class=\"slide-header\">".$titles[$i]."</div>\n" ;
							//$titles[$i] = strip_tags($titles[$i]) ;
						//}
						//$html_slide .= $slide ;
						$slides[$i]['html'] = $slide ;
						$slides[$i]['title'] = strip_tags($titles[$i]) ;
					}
				}
			}
		}
		
		$buttons = '';
		//si la fonction est appelee par le handler diaporama, on ajoute les liens d'edition et de retour
		if ($GLOBALS['wiki']->GetMethod() == "diaporama") {
			$buttons .= '<a class="btn" href="'.$GLOBALS['wiki']->href('',$pagetag).'">&times;</a>'."\n";
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
		
		return $output;
	}
}

function show_form_theme_selector($mode = 'selector') {
	// en mode ?dition on recup?re aussi les images de fond
	if ($mode=='edit') {
		$id = 'form_graphical_options'; 
		// r?cup?ration des images de fond
		$backgroundsdir = 'files/backgrounds';
		$dir = (is_dir($backgroundsdir) ? opendir($backgroundsdir) : false);
		while ($dir && ($file = readdir($dir)) !== false) {	
				$imgextension = strtolower(substr($file, -4, 4));  	
				// les jpg sont les fonds d'?crans, ils doivent ?tre mis en miniature
				if ($imgextension == '.jpg') {
					if (!is_file($backgroundsdir.'/thumbs/'.$file)) {
						require_once 'tools/attach/libs/class.imagetransform.php';
						$imgTrans = new imageTransform();
						$imgTrans->sourceFile = $backgroundsdir.'/'.$file;		
						$imgTrans->targetFile = $backgroundsdir.'/thumbs/'.$file;
						$imgTrans->resizeToWidth = 100;
						$imgTrans->resizeToHeight = 75;
						if ($imgTrans->resize()) {
							$backgrounds[] = $imgTrans->targetFile;
						}
					} else {
						$backgrounds[] = $backgroundsdir.'/thumbs/'.$file;
					}
				}
				// les png sont les images ? r?p?ter en mosaique
				elseif ($imgextension == '.png') {
					$backgrounds[] = $backgroundsdir.'/'.$file;
				}
		}
		if ($dir) closedir($dir);
		
		$bgselector = '';
		
		if (isset($backgrounds) && is_array($backgrounds)) {
			$bgselector .= '<h3>'.TEMPLATE_BG_IMAGE.'</h3>
			<div id="bgCarousel" class="carousel" data-interval="5000" data-pause="true">
	    <!-- Carousel items -->
	    <div class="carousel-inner">'."\n";
			$nb = 0; $thumbs_per_slide = 8; $firstitem = true;
			sort($backgrounds);
			foreach($backgrounds as $background) {
				$nb++;
				if ($nb == 1) {
					$bgselectorlist = '';
					$class = '';
				}

				// dans le cas ou il n'y a pas d'image de fond selectionnee on bloque la premiere diapo
				if ($GLOBALS['wiki']->config['favorite_background_image'] == '' && $firstitem) {
					$class = ' active';
					$firstitem = false;
				}

				$choosen = ($background == 'files/backgrounds/'.$GLOBALS['wiki']->config['favorite_background_image']);
				if ($choosen) $class = ' active';

				$imgextension = strtolower(substr($background, -4, 4));

				if ($imgextension=='.jpg') {
					$bgselectorlist .= '<img class="bgimg'.($choosen ? ' choosen' : '').'" src="'.$background.'" width="100" height="75" />'."\n";
				} 
				elseif ($imgextension=='.png') {
					$bgselectorlist .= '<div class="mozaicimg'.($choosen ? ' choosen' : '').'" style="background:url('.$background.') repeat top left;"></div>'."\n";
				}
				// on finit la diapositive			
				if ($nb == $thumbs_per_slide) {
					$nb=0;
					$bgselector .= '<div class="item'.$class.'">'."\n".$bgselectorlist.'</div>'."\n";
				}
			}
			// si la boucle se termine et qu'on ne vient pas de finir une diapositive
			if ($nb != 0) {
				$bgselector .= '<div class="item'.$class.'">'."\n".$bgselectorlist.'</div>'."\n";
			}
			$bgselector .= '</div>
	    <!-- Carousel nav -->
	    <a class="carousel-control left" href="#bgCarousel" data-slide="prev">&lsaquo;</a>
	    <a class="carousel-control right" href="#bgCarousel" data-slide="next">&rsaquo;</a>
	    </div>'."\n";
		}
	}
	else {
		$id = 'form_theme_selector';
		$bgselector = '';
	}

	$selecteur = '<form class="form-horizontal" id="'.$id.'">'."\n";
	
	//on cherche tous les dossiers du repertoire themes et des sous dossier styles et squelettes, et on les range dans le tableau $wakkaConfig['templates']
	$repertoire_initial = 'tools'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'themes';
	$GLOBALS['wiki']->config['templates'] = search_template_files($repertoire_initial);

	//s'il y a un repertoire themes a la racine, on va aussi chercher les templates dedans
	if (is_dir('themes')) {
		$repertoire_racine = 'themes';
		$GLOBALS['wiki']->config['templates'] = array_merge($GLOBALS['wiki']->config['templates'], search_template_files($repertoire_racine));
		if (is_array($GLOBALS['wiki']->config['templates'])) ksort($GLOBALS['wiki']->config['templates']);
	}


	$selecteur .= '<div class="control-group">'."\n".
					'<label class="control-label">'.TEMPLATE_THEME.'</label>'."\n".
					'<div class="controls">'."\n".
					'<select id="changetheme" name="theme">'."\n";
    foreach(array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
            if($value !== $GLOBALS['wiki']->config['favorite_theme']) {
                    $selecteur .= '<option value="'.$value.'">'.$value.'</option>'."\n";
            }
            else {
                    $selecteur .= '<option value="'.$value.'" selected="selected">'.$value.'</option>'."\n";
            }
    }
    $selecteur .= '</select>'."\n".'</div>'."\n".'</div>'."\n";
	
	$selecteur .= '<div class="control-group">'."\n".
					'<label class="control-label">'.TEMPLATE_SQUELETTE.'</label>'."\n".
					'<div class="controls">'."\n".
					'<select id="changesquelette" name="squelette">'."\n";
	ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette']);
    foreach($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['squelette'] as $key => $value) {
            if($value !== $GLOBALS['wiki']->config['favorite_squelette']) {
                    $selecteur .= '<option value="'.$key.'">'.$value.'</option>'."\n";
            }
            else {
                    $selecteur .= '<option value="'.$GLOBALS['wiki']->config['favorite_squelette'].'" selected="selected">'.$value.'</option>'."\n";
            }
    }
    $selecteur .= '</select>'."\n".'</div>'."\n".'</div>'."\n";

	ksort($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style']);	
	$selecteur .= '<div class="control-group">'."\n".
					'<label class="control-label">'.TEMPLATE_STYLE.'</label>'."\n".
					'<div class="controls">'."\n".
					'<select id="changestyle" name="style">'."\n";
    foreach($GLOBALS['wiki']->config['templates'][$GLOBALS['wiki']->config['favorite_theme']]['style'] as $key => $value) {
            if($value !== $GLOBALS['wiki']->config['favorite_style']) {
                    $selecteur .= '<option value="'.$key.'">'.$value.'</option>'."\n";
            }
            else {	            		
                    $selecteur .= '<option value="'.$GLOBALS['wiki']->config['favorite_style'].'" selected="selected">'.$value.'</option>'."\n";
            }
    }
    $selecteur .= 	'</select>'."\n".'</div>'."\n".'</div>'."\n".$bgselector."\n".
					'</form>'."\n";

	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').add_templates_list_js()."\n";
	return $selecteur;
}


function add_templates_list_js() {
	// AJOUT DU JAVASCRIPT QUI PERMET DE CHANGER DYNAMIQUEMENT DE TEMPLATES
	$js = '<script>
	var tab1 = new Array();
	var tab2 = new Array();'."\n";
	foreach(array_keys($GLOBALS['wiki']->config['templates']) as $key => $value) {
            $js .= '		tab1["'.$value.'"] = new Array(';
            $nbocc=0;	           
            foreach($GLOBALS['wiki']->config['templates'][$value]["squelette"] as $key2 => $value2) {
            	if ($nbocc==0) $js .= '\''.$value2.'\'';
            	else $js .= ',\''.$value2.'\'';
            	$nbocc++;
            }
            $js .= ');'."\n";
            
            $js .= '		tab2["'.$value.'"] = new Array(';
            $nbocc=0;
            foreach($GLOBALS['wiki']->config['templates'][$value]["style"] as $key3 => $value3) {
            	if ($nbocc==0) $js .= '\''.$value3.'\'';
            	else $js .= ',\''.$value3.'\'';
            	$nbocc++;
            }
            $js .= ');'."\n";	      
    }	
    $js .= '</script>'."\n";

	return $js;
}
?>
