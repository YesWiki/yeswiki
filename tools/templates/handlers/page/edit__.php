<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                        |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2012 Outils-Réseaux (accueil@outils-reseaux.org)                                       |
// +------------------------------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                                        |
// | modify it under the terms of the GNU Lesser General Public                                           |
// | License as published by the Free Software Foundation; either                                         |
// | version 2.1 of the License, or (at your option) any later version.                                   |
// |                                                                                                      |
// | This library is distributed in the hope that it will be useful,                                      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                                       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU                                    |
// | Lesser General Public License for more details.                                                      |
// |                                                                                                      |
// | You should have received a copy of the GNU Lesser General Public                                     |
// | License along with this library; if not, write to the Free Software                                  |
// | Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA                            |
// +------------------------------------------------------------------------------------------------------+
// 
/**
* Edition du Yeswiki
*
*@package 		templates
*@author        Florian Schmitt <florian@outils-reseaux.org>
*@copyright     2012 Outils-Réseaux
*/

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

// on enleve l'action template
$plugin_output_new = preg_replace ("/".'(\\{\\{template)'.'(.*?)'.'(\\}\\})'."/is", '', $plugin_output_new);

// on enleve les restes de wikini : script obscur de la barre de redaction
$plugin_output_new = str_replace("<script type=\"text/javascript\">\n".
				"document.getElementById(\"body\").onkeydown=fKeyDown;\n".
				"</script>\n", '', $plugin_output_new);

// personnalisation graphique que dans le cas ou on est autorisé
if ((!isset($this->config['hide_action_template']) or (isset($this->config['hide_action_template']) && !$this->config['hide_action_template'])) && 
	($this->HasAccess("write") && $this->HasAccess("read") && (!SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME || (SEUL_ADMIN_ET_PROPRIO_CHANGENT_THEME && ($this->UserIsAdmin() || $this->UserIsOwner() ) ) ) ) ) { 
	$selecteur = 	'<div id="graphical_options" class="modal fade">'."\n".
					'<div class="modal-header">'."\n".
						'<a class="close" data-dismiss="modal">&times;</a>'."\n".
						'<h3>'.TEMPLATE_CUSTOM_GRAPHICS.' '.$this->GetPageTag().'</h3>'."\n".
					'</div>'."\n".
					'<div class="modal-body">'."\n".
					'<form class="form-horizontal" id="form_graphical_options">'."\n";

	// récupération des images de fond
	$backgroundsdir = 'files/backgrounds';		
	if (is_dir($backgroundsdir)) {
		$dir = opendir($backgroundsdir);
		while (false !== ($file = readdir($dir))) { 
			$imgextension = strtolower(substr($file, -4, 4));  	
			// les jpg sont les fonds d'écrans, ils doivent être mis en miniature
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
			// les png sont les images à répéter en mosaique
			elseif ($imgextension == '.png') {
				$backgrounds[] = $backgroundsdir.'/'.$file;
			}
		}
		closedir($dir);
	}

	
	$bgselector = '';
	
	if (isset($backgrounds) && is_array($backgrounds)) {
		$bgselector .= '<h3>'.TEMPLATE_BG_IMAGE.'</h3>
		<div id="bgCarousel" class="carousel" data-interval="5000" data-pause="true">
    <!-- Carousel items -->
    <div class="carousel-inner">';
			   $nb=0; $class="active "; sort($backgrounds);
			   foreach($backgrounds as $background) {
					$nb++;
					if ($nb == 1) {$bgselector .= '<div class="'.$class.'item">';$class='';}
					$imgextension = strtolower(substr($background, -4, 4));
					if ($imgextension=='.jpg') {
						$bgselector .= '<img class="bgimg" src="'.$background.'" />';
					} elseif ($imgextension=='.png') {
						$bgselector .= '<div class="mozaicimg" style="background:url('.$background.') repeat top left;"></div>';
					}
					
					if ($nb == 8) {$nb=0;$bgselector .= '</div>';}
			  }
			  if ($nb != 0) {$bgselector .= '</div>';}
			   $bgselector .= '</div>
    <!-- Carousel nav -->
    <a class="carousel-control left" href="#bgCarousel" data-slide="prev">&lsaquo;</a>
    <a class="carousel-control right" href="#bgCarousel" data-slide="next">&rsaquo;</a>
    </div>';
	}
	$bgselector .= '';
	
	// Edition
	if (!isset($_POST["submit"]) || (isset($_POST["submit"]) && $_POST["submit"] != html_entity_decode('Aper&ccedil;u') && $_POST["submit"] != 'Sauver')) {

		//on cherche tous les dossiers du repertoire themes et des sous dossier styles et squelettes, et on les range dans le tableau $wakkaConfig['templates']
		$repertoire_initial = 'tools'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.'themes';
		$this->config['templates'] = search_template_files($repertoire_initial);

		//s'il y a un repertoire themes a la racine, on va aussi chercher les templates dedans
		if (is_dir('themes')) {
			$repertoire_racine = 'themes';
			$this->config['templates'] = array_merge($this->config['templates'], search_template_files($repertoire_racine));
			if (is_array($this->config['templates'])) ksort($this->config['templates']);
		}


		$selecteur .= '<div class="control-group">'."\n".
						'<label class="control-label">'.TEMPLATE_THEME.'</label>'."\n".
						'<div class="controls">'."\n".
						'<select id="changetheme" name="theme">'."\n";
	    foreach(array_keys($this->config['templates']) as $key => $value) {
	            if($value !== $this->config['favorite_theme']) {
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
		ksort($this->config['templates'][$this->config['favorite_theme']]['squelette']);
	    foreach($this->config['templates'][$this->config['favorite_theme']]['squelette'] as $key => $value) {
	            if($value !== $this->config['favorite_squelette']) {
	                    $selecteur .= '<option value="'.$key.'">'.$value.'</option>'."\n";
	            }
	            else {
	                    $selecteur .= '<option value="'.$this->config['favorite_squelette'].'" selected="selected">'.$value.'</option>'."\n";
	            }
	    }
	    $selecteur .= '</select>'."\n".'</div>'."\n".'</div>'."\n";

		ksort($this->config['templates'][$this->config['favorite_theme']]['style']);	
		$selecteur .= '<div class="control-group">'."\n".
						'<label class="control-label">'.TEMPLATE_STYLE.'</label>'."\n".
						'<div class="controls">'."\n".
						'<select id="changestyle" name="style">'."\n";
	    foreach($this->config['templates'][$this->config['favorite_theme']]['style'] as $key => $value) {
	            if($value !== $this->config['favorite_style']) {
	                    $selecteur .= '<option value="'.$key.'">'.$value.'</option>'."\n";
	            }
	            else {	            		
	                    $selecteur .= '<option value="'.$this->config['favorite_style'].'" selected="selected">'.$value.'</option>'."\n";
	            }
	    }
	    $selecteur .= 	'</select>'."\n".'</div>'."\n".'</div>'."\n".$bgselector."\n".
						'</form>'."\n".'</div>'."\n".
						'<div class="modal-footer">'."\n".
							'<a href="#" class="btn button_cancel" data-dismiss="modal">'.TEMPLATE_CANCEL.'</a>'."\n".
							'<a href="#" class="btn btn-primary button_save" data-dismiss="modal">'.TEMPLATE_APPLY.'</a>'."\n".						
						'</div>'."\n".	
					'</div>'."\n";
		
		//AJOUT DU JAVASCRIPT QUI PERMET DE CHANGER DYNAMIQUEMENT DE TEMPLATES			
		$js = '<script type="text/javascript"><!--
		var tab1 = new Array();
		var tab2 = new Array();'."\n";
		foreach(array_keys($this->config['templates']) as $key => $value) {
	            $js .= '		tab1["'.$value.'"] = new Array(';
	            $nbocc=0;	           
	            foreach($this->config['templates'][$value]["squelette"] as $key2 => $value2) {
	            	if ($nbocc==0) $js .= '\''.$value2.'\'';
	            	else $js .= ',\''.$value2.'\'';
	            	$nbocc++;
	            }
	            $js .= ');'."\n";
	            
	            $js .= '		tab2["'.$value.'"] = new Array(';
	            $nbocc=0;
	            foreach($this->config['templates'][$value]["style"] as $key3 => $value3) {
	            	if ($nbocc==0) $js .= '\''.$value3.'\'';
	            	else $js .= ',\''.$value3.'\'';
	            	$nbocc++;
	            }
	            $js .= ');'."\n";	      
	    }
				
		$js .= '</script>'."\n".'<script type="text/javascript" src="tools/templates/libs/templates_edit.js"></script>'."\n";

		//quand le changement des valeurs du template est caché, il faut stocker les valeurs déja entrées pour ne pas retourner au template par défaut
		$selecteur .= '<input id="hiddentheme" type="hidden" name="theme" value="'.$this->config['favorite_theme'].'" />'."\n";
		$selecteur .= '<input id="hiddensquelette" type="hidden" name="squelette" value="'.$this->config['favorite_squelette'].'" />'."\n";
		$selecteur .= '<input id="hiddenstyle" type="hidden" name="style" value="'.$this->config['favorite_style'].'" />'."\n";
		$selecteur .= '<input id="hiddenbgimg" type="hidden" name="bgimg" value="'.$this->config['favorite_background_image'].'" />'."\n";


		// on rajoute la personnalisation graphique
		$plugin_output_new = preg_replace('/<\/body>/', $selecteur."\n".$js."\n".'</body>', $plugin_output_new);
		$changetheme = TRUE;
	}
	else {
		$changetheme = FALSE;
	}

	
	// le bouton aperçu c'est pour les vieilles versions de wikini, on en profite pour rajouter des classes pour colorer les boutons et la personnalisation graphique
	$patterns = array(	0 => 	'/<input name=\"submit\" type=\"submit\" value=\"Sauver\" accesskey=\"s\" \/>/',
						1 => 	'/<input name=\"submit\" type=\"submit\" value=\"Aper\&ccedil;u\" accesskey=\"p\" \/>/',
						2 => 	'/<input type=\"button\" value=\"Annulation\" onclick=\"document.location=\'' . preg_quote(addslashes($this->href()), '/') . '\';\" \/>/'
						);
	$replacements = array(
						0 => 	'<div class="form-actions">'."\n".'<button type="submit" name="submit" value="Sauver" class="btn btn-primary">'.TEMPLATE_SAVE.'</button>',
						1 => 	'', 
						2 => 	'<button class="btn" onclick="location.href=\''.addslashes($this->href()).'\';return false;">'.TEMPLATE_CANCEL.'</button>'."\n".
								(($changetheme) ? '<button class="btn btn-info offset1" data-toggle="modal" data-target="#graphical_options" data-backdrop="false">'.TEMPLATE_THEME.'</button>'."\n".'</div>' : '') // le bouton Theme du bas de l'interface d'edition
						);
	$plugin_output_new = preg_replace($patterns, $replacements, $plugin_output_new);
}

?>
