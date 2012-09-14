<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 4.1                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2004 Outils-Reseaux (accueil@outils-reseaux.org)                                       |
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
// CVS : $Id: formulaire.fonct.inc.php,v 1.27 2011-07-13 10:33:23 mrflos Exp $
/**
* Formulaire
*
* Les fonctions de mise en page des formulaire
*
*@package bazar
//Auteur original :
*@author        Florian SCHMITT <florian@outils-reseaux.org>
//Autres auteurs :
*@author        Aleandre GRANIER <alexandre@tela-botanica.org>
*@copyright     Tela-Botanica 2000-2004
*@version       $Revision: 1.27 $ $Date: 2011-07-13 10:33:23 $
// +------------------------------------------------------------------------------------------------------+
*/

//comptatibilite avec PHP4...
if (version_compare(phpversion(), '5.0') < 0) {
    eval('
    function clone($object) {
      return $object;
    }
    ');
}


/** afficher_image() - genere une image en cache (gestion taille et vignettes) et l'affiche comme il faut
*
* @param    string	nom du fichier image
* @param	string	label pour l'image
* @param    string	classes html supplementaires
* @param    int		largeur en pixel de la vignette
* @param    int		hauteur en pixel de la vignette
* @param    int		largeur en pixel de l'image redimensionnee
* @param    int		hauteur en pixel de l'image redimensionnee
* @return   void
*/
function afficher_image($nom_image, $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image) {
	//faut il creer la vignette?
	if ($hauteur_vignette!='' && $largeur_vignette!='')	{
		//la vignette n'existe pas, on la genere
		if (!file_exists('cache/vignette_'.$nom_image)) {
			$adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/vignette_'.$nom_image, $largeur_vignette, $hauteur_vignette);
		}
		list($width, $height, $type, $attr) = getimagesize('cache/vignette_'.$nom_image);
		//faut il redimensionner l'image?
		if ($hauteur_image!='' && $largeur_image!='') {
			//l'image redimensionnee n'existe pas, on la genere
			if (!file_exists('cache/image_'.$nom_image)) {
				$adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/image_'.$nom_image, $largeur_image, $hauteur_image);
			}
			//on renvoit l'image en vignette, avec quand on clique, l'image redimensionnee
			$url_base = str_replace('wakka.php?wiki=','',$GLOBALS['wiki']->config['base_url']);
			return  '<a class="triggerimage'.' '.$class.'" rel="#overlay_bazar" title="'.$label.'" href="'.$url_base.'cache/image_'.$nom_image.'">'."\n".
					'<img alt="'.$nom_image.'"'.' src="'.$url_base.'cache/vignette_'.$nom_image.'" width="'.$width.'" height="'.$height.'" rel="'.$url_base.'cache/image_'.$nom_image.'" />'."\n".
					'</a>'."\n";
		}
		else {
			//on renvoit l'image en vignette, avec quand on clique, l'image originale
			return  '<a class="triggerimage'.' '.$class.'" rel="#overlay_bazar" title="'.$label.'" href="'.$url_base.BAZ_CHEMIN_UPLOAD.$nom_image.'">'."\n".
					'<img alt="'.$nom_image.'"'.' src="'.$url_base.'cache/vignette_'.$nom_image.'" width="'.$width.'" height="'.$height.'" rel="'.$url_base.'cache/image_'.$nom_image.'" />'."\n".
					'</a>'."\n";
		}
	}
	//pas de vignette, mais faut il redimensionner l'image?
	else if ($hauteur_image!='' && $largeur_image!='') {
		//l'image redimensionnee n'existe pas, on la genere
		if (!file_exists('cache/image_'.$nom_image)) {
			$adr_img = redimensionner_image(BAZ_CHEMIN_UPLOAD.$nom_image, 'cache/image_'.$nom_image, $largeur_image, $hauteur_image);
		}
		//on renvoit l'image redimensionnee
		list($width, $height, $type, $attr) = getimagesize('cache/image_'.$nom_image);
		return  '<img class="'.$class.'" alt="'.$nom_image.'"'.' src="cache/image_'.$nom_image.'" width="'.$width.'" height="'.$height.'" />'."\n";
		
	}
	//on affiche l'image originale sinon
	else {
		list($width, $height, $type, $attr) = getimagesize(BAZ_CHEMIN_UPLOAD.$nom_image);
		return  '<img class="'.$class.'" alt="'.$nom_image.'"'.' src="'.BAZ_CHEMIN_UPLOAD.$nom_image.'" width="'.$width.'" height="'.$height.'" />'."\n";
	}
}

function redimensionner_image($image_src, $image_dest, $largeur, $hauteur) {
	require_once 'tools/bazar/libs/class.imagetransform.php';
	$imgTrans = new imageTransform();
	$imgTrans->sourceFile = $image_src;
	$imgTrans->targetFile = $image_dest;
	$imgTrans->resizeToWidth = $largeur;
	$imgTrans->resizeToHeight = $hauteur;
	if (!$imgTrans->resize()) {
		// in case of error, show error code
		return $imgTrans->error;
	// if there were no errors
	} else {
		return $imgTrans->targetFile;
	}
}

//-------------------FONCTIONS DE TRAITEMENT DU TEMPLATE DU FORMULAIRE

/** formulaire_valeurs_template_champs() - Decoupe le template et renvoie un tableau structure
*
* @param    string  Template du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
/*function formulaire_valeurs_template_champs($template) {
	//Parcours du template, pour mettre les champs du formulaire avec leurs valeurs specifiques
	$tableau_template= array();
	$nblignes=0;
	//on traite le template ligne par ligne
	$chaine = explode ("\n", $template);
	foreach ($chaine as $ligne) {
		if ($ligne!='') {
			//on decoupe chaque ligne par le separateur *** (c'est historique)
			$tableau_template[$nblignes] = array_map("trim", explode ("***", $ligne));
			if (!isset($tableau_template[$nblignes][9])) $tableau_template[$nblignes][9] = '';
			if (!isset($tableau_template[$nblignes][10])) $tableau_template[$nblignes][10] = '';
			$nblignes++;
		}
	}
	return $tableau_template;
}*/

//-------------------FONCTIONS DE MISE EN PAGE DES FORMULAIRES

/** liste() - Ajoute un element de type liste deroulante au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function liste(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ($mode=='saisie')
	{
		$bulledaide = '';
		if (isset($tableau_template[10]) && $tableau_template[10]!='') {
			$bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		}
			
		$select_html = '<div class="form_line">'."\n".'<div class="formulaire_label">'."\n";
		if (isset($tableau_template[8]) && $tableau_template[8]==1) {
			$select_html .= '<span class="required_symbol">*&nbsp;</span>'."\n";
		}
		$select_html .= $tableau_template[2].$bulledaide.' : </div>'."\n".'<div class="form_input">'."\n".'<select';		
		
		$select_attributes = '';
		
		if ($tableau_template[4] != '' && $tableau_template[4] > 1) {
			$select_attributes .= ' multiple="multiple" size="'.$tableau_template[4].'"';
			$selectnametab = '[]';
		} else {
			$selectnametab = '';
		}
		
		$select_attributes .= ' class="bazar-select" id="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" name="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].$selectnametab.'"';
				
		
		if (isset($tableau_template[8]) && $tableau_template[8]==1) {
			$select_attributes .= ' required="required"';
		}
		$select_html .= $select_attributes.'>'."\n";
		
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			$def =	$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
		}
		else
		{
			$def = $tableau_template[5];
		}
		
		$valliste = baz_valeurs_liste($tableau_template[1]);
		if ($def=='' && ($tableau_template[4] == '' || $tableau_template[4] <= 1)) {
			$select_html .= '<option value="0" selected="selected">'.BAZ_CHOISIR.'</option>'."\n"; 
		} 
		if (is_array($valliste['label'])) {
			foreach ($valliste['label'] as $key => $label) {
				$select_html .= '<option value="'.$key.'"';
				if ($def != '' && strstr($key, $def)) $select_html .= ' selected="selected"';
				$select_html .= '>'.$label.'</option>'."\n";
			}

		}
		
		$select_html .= "</select>\n</div>\n</div>\n";
		
		$formtemplate .= $select_html;

		
	}
	elseif ($mode == 'requete')
	{
	
	}
	elseif ($mode == 'formulaire_recherche')
	{
		//on affiche la liste sous forme de liste deroulante
		if ($tableau_template[9]==1)
		{
			$valliste = baz_valeurs_liste($tableau_template[1]);
			
			$select[0] = BAZ_INDIFFERENT;
			if (is_array($valliste['label'])) {
				$select = $select + $valliste['label'];
			}
/*
			echo '<br />'.$tableau_template[1].'<br />';
			var_dump($select);
*/
			$select = '<div class="form_line">'."\n".
					'	<div class="formulaire_label">'.$tableau_template[2].'</div>'."\n".
					'	<div class="form_input">'."\n".
					'		<select name="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" id="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'">'."\n".
					'		</select>'."\n".
					'	</div>'."\n".
					'</div>'."\n";	
			return $select ;			
		}
		
		//on affiche la liste sous forme de checkbox
		elseif ($tableau_template[9]==2)
		{
			// valeurs de la liste
			$valliste = baz_valeurs_liste($tableau_template[1]);
			
			$squelette_checkbox = '';
			if ($valliste) {
				$choixcheckbox = $valliste['label'];
				//var_dump($valliste);			
				$squelette_checkbox .= '<fieldset class="bazar_fieldset">'."\n".'<legend>'. $tableau_template[2] .'</legend>'."\n";
				
				// generation de la liste des checkbox
				foreach ($choixcheckbox as $id => $label) {
					$squelette_checkbox .=  '<div class="bazar_checkbox">'."\n".
												'<input type="checkbox" id="checkbox_'.$tableau_template[1].$id.'" value="1" name="checkbox'.$tableau_template[1].'['.$id.']" class="element_checkbox">'."\n".
												'<label for="checkbox_'.$tableau_template[1].$id.'">'.$label.'</label>'."\n".
											'</div>';
				}
				
				$squelette_checkbox .= '</fieldset> '."\n";
			}
	
			return $squelette_checkbox;			
		}
	}
	elseif ($mode == 'requete_recherche')
	{
		if ($tableau_template[9]==1 && isset($_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]] != 0)
		{
			/*return ' AND bf_id_fiche IN (SELECT bfvt_ce_fiche FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_id_element_form="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'" AND bfvt_texte="'.$_REQUEST[$tableau_template[0].$tableau_template[1].$tableau_template[6]].'") ';*/
		}
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			$valliste = baz_valeurs_liste($tableau_template[1]);
			
			if (isset($valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]]))
			{
				$html = '<div class="BAZ_rubrique">'."\n".
						'<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n".
						'<span class="BAZ_texte">'."\n".
						$valliste["label"][$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]]."\n".
						'</span>'."\n".
						'</div>'."\n";
			}
		}
		return $html;
	}
}

/** checkbox() - Ajoute un element de type case a cocher au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element case a cocher
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function checkbox(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ($mode == 'saisie')
	{
		// on ajout la bulle d'aide si elle existe
		$bulledaide = '';
		if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		
		// valeurs de la liste
		$valliste = baz_valeurs_liste($tableau_template[1]);
		$choixcheckbox = $valliste['label'];
		
		if ($choixcheckbox == NULL) {
			return '<div class="error_box">'.BAZ_LISTE_NON_TROUVEE.' : '.$tableau_template[1].'.</div>'."\n";
		} 
		else {
			// valeurs par defauts
			if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
				$tab = explode( ',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] );
			} else {
				$tab = explode( ',', $tableau_template[5] );
			}
				
			$squelette_checkbox = '<fieldset class="bazar_fieldset">'."\n".'<legend>'. $tableau_template[2].$bulledaide;
			if (isset($tableau_template[8]) && $tableau_template[8]==1) {
				$squelette_checkbox .= '<span class="required_symbol">&nbsp;*</span>';
			}
			$squelette_checkbox .= '</legend>'."\n";
			
			// generation de la liste des checkbox
			foreach ($choixcheckbox as $id => $label) {
				// teste si la valeur de la liste doit etre cochee par defaut
				if (in_array($id,$tab)) {
					$defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$id.']'] = true;				
				} else {
					$defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$id.']'] = false;
				}
				$squelette_checkbox .= '<div class="bazar_checkbox">'."\n"."\n".'</div>';
			}
			
			$squelette_checkbox .= '</fieldset> '."\n";

			return $squelette_checkbox;
		}
	}
	elseif ( $mode == 'requete' )
	{
	}
	elseif ($mode == 'formulaire_recherche')
	{
		if ($tableau_template[9]==1)
		{
			// valeurs de la liste
			$valliste = baz_valeurs_liste($tableau_template[1]);
			
			$squelette_checkbox = '';
			if ($valliste) {
				$choixcheckbox = $valliste['label'];
				//var_dump($valliste);			
				$squelette_checkbox .= '<fieldset class="bazar_fieldset">'."\n".'<legend>'. $tableau_template[2] .'</legend>'."\n";
				
				// generation de la liste des checkbox
				foreach ($choixcheckbox as $id => $label) {
					$squelette_checkbox .=  '<div class="bazar_checkbox">'."\n".
												'<input type="checkbox" id="checkbox_'.$tableau_template[1].$id.'" value="1" name="checkbox'.$tableau_template[1].'['.$id.']" class="element_checkbox">'."\n".
												'<label for="checkbox_'.$tableau_template[1].$id.'">'.$label.'</label>'."\n".
											'</div>';
				}
				
				$squelette_checkbox .= '</fieldset> '."\n";
			}
	
			return $squelette_checkbox;
		}
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			$valliste = baz_valeurs_liste($tableau_template[1]);

			$tabresult = explode(',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
			if (is_array($tabresult)) {
				$labels_result = '';
				foreach ($tabresult as $id)
				if (isset($valliste["label"][$id])) {
					if ($labels_result == '') $labels_result = $valliste["label"][$id];
					else $labels_result .= ', '.$valliste["label"][$id];
				}
			}
			 
			{
				$html = '<div class="BAZ_rubrique">'."\n".
						'<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n".
						'<span class="BAZ_texte">'."\n".
						$labels_result."\n".
						'</span>'."\n".
						'</div>'."\n";
			}
		}
		return $html;
	}
}

/** jour() - Ajoute un element de type date au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element date
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function jour(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ( $mode == 'saisie')
	{
		$bulledaide = '';
		if (isset($tableau_template[10]) && $tableau_template[10]!='') {
			$bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		}
			
		$date_html = '<div class="form_line">'."\n".'<div class="formulaire_label">'."\n";
		if (isset($tableau_template[8]) && $tableau_template[8]==1) {
			$date_html .= '<span class="required_symbol">*&nbsp;</span>'."\n";
		}
		$date_html .= $tableau_template[2].$bulledaide.' : </div>'."\n".'<div class="form_input">'."\n".'<input type="date" name="'.$tableau_template[1].'" ';		
		
		
		$date_html .= ' class="bazar-date input_text" id="'.$tableau_template[1].'"';
				
		
		if (isset($tableau_template[8]) && $tableau_template[8]==1) {
			$date_html .= ' required="required"';
		}
	
		//gestion des valeurs par defaut pour modification
		if (isset($valeurs_fiche[$tableau_template[1]]))
		{
			$date_html .= ' value="'.$valeurs_fiche[$tableau_template[1]].'" />';
		}
		else
		{
			//gestion des valeurs par defaut (date du jour)
			if (isset($tableau_template[5]) && $tableau_template[5]!='') {
				$date_html .= ' value="'.$tableau_template[5].'" />';
			}

			else {
				$date_html .= ' value="0" />';
			}
		}
		$date_html .= '</div>'."\n".'</div>'."\n";

		$formtemplate->addElement('html', $date_html) ;
		
	}
	elseif ( $mode == 'requete' )
	{
		return array($tableau_template[1] => $_POST[$tableau_template[1]]);				
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{
		$res = '<div class="BAZ_rubrique  BAZ_rubrique_'.$GLOBALS['_BAZAR_']['class'].'">'."\n".
				'<span class="BAZ_label BAZ_label_'.$GLOBALS['_BAZAR_']['class'].'">'.$tableau_template[2].'&nbsp;:</span>'."\n";
		$res .= '<span class="BAZ_texte BAZ_texte_'.$GLOBALS['_BAZAR_']['class'].'">'.strftime('%d.%m.%Y',strtotime($valeurs_fiche[$tableau_template[1]])).'</span>'."\n".'</div>'."\n";
		return $res;
	}
}

/** listedatedeb() - voir date()
*
*/
function listedatedeb(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	return jour($formtemplate, $tableau_template , $mode, $valeurs_fiche);
}

/** listedatefin() - voir date()
*
*/
function listedatefin(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	return jour($formtemplate, $tableau_template , $mode, $valeurs_fiche);
}

/** tags() - Ajoute un element de type mot cles (tags)
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @param    mixed   valeur par defaut du champs
* @return   void
*/
function tags(&$formtemplate, $tableau_template, $mode, $valeurs_fiche) {
	list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
	if ( $mode == 'saisie' )
	{
		$tags_javascript = '';
		//gestion des mots cles deja entres
		if (isset($valeurs_fiche[$tableau_template[1]])) {
			$tags = explode(",", mysql_real_escape_string($valeurs_fiche[$tableau_template[1]]));
			if (is_array($tags))
			{
				sort($tags);
				foreach ($tags as $tag) 
				{
					$tags_javascript .= 't.add(\''.$tag.'\');'."\n";
				}
			}
		}		
		
		$formtag = '<script defer src="tools/tags/libs/GrowingInput.js" type="text/javascript" charset="utf-8"></script>
		<script defer src="tools/tags/libs/tags_suggestions.js" type="text/javascript" charset="utf-8"></script>
		<script defer type="text/javascript">
		$(document).ready(function() {
			// Autocompletion des mots cles	
			var t = new $.TextboxList(\'#'.$tableau_template[1].'\', {unique:true, inBetweenEditableBits:false, plugins:{autocomplete: {
				minLength: 1,
				queryRemote: true,
				remote: {url: \''.$GLOBALS['wiki']->href('json',$GLOBALS['wiki']->GetPageTag()).'\'}
			}}});
			
			
			'.$tags_javascript.'		
		});
		</script>';
		$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$formtag."\n";
		
		// on prepare le html de la bulle d'aide, si elle existe
		if ($bulle_d_aide != '') {
			$bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		} else {
			$bulledaide = '';
		}
		
		//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
		//puis s'il y a une variable passee en GET,
		//enfin on prend la valeur par defaut du formulaire sinon
		if (isset($valeurs_fiche[$identifiant])) {
			$defauts = $valeurs_fiche[$identifiant];
		}
		elseif (isset($_GET[$identifiant])) {
			$defauts = stripslashes($identifiant);
		} else {
			$defauts = stripslashes($valeur_par_defaut);
		}

		//si la valeur de nb_max_car est vide, on la mets au maximum
		if ($nb_max_car == '') $nb_max_car = 255;
		
		//par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
		if ($type_input == '') $type_input = 'text';
		
		$input_html  = '<div class="form_line">'."\n".'<label>';
		$input_html .= ($obligatoire == 1) ? '<span class="required_symbol">*&nbsp;</span>' : '';
		$input_html .= $label.$bulledaide.'</label>'."\n";
		$input_html .= '<div class="form_input">'."\n";
		$input_html .= '<input type="'.$type_input.'"';
		$input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
		$input_html .= ' name="'.$identifiant.'" class="input_text microblog_toustags" id="'.$identifiant.'"';
		$input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
		$input_html .= ($type_input == 'number' && $nb_min_car != '') ? ' min="'.$nb_min_car.'"' : '';
		$input_html .= ($type_input == 'number') ? ' max="'.$nb_max_car.'"' : '';	
		$input_html .= ($regexp != '') ? ' pattern="'.$regexp.'"' : '';
		$input_html .= ($obligatoire == 1) ? ' required="required"' : '';
		$input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";
		
		//mots cles caches
		$tags_caches = '<input id="mots_cles_caches" name="mots_cles_caches" type="hidden" value="'.trim($tableau_template[5]).'" />'."\n";
		
		return $input_html.$tags_caches;	

	}
	elseif ( $mode == 'requete' ) {
		//on supprime les tags existants
		$GLOBALS['wiki']->DeleteTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', NULL, '', '');
		//on decoupe les tags pour les mettre dans un tableau
		$liste_tags = ($valeurs_fiche['mots_cles_caches'] ? $valeurs_fiche['mots_cles_caches'].',' : '').$valeurs_fiche[$tableau_template[1]];		
		$tags = explode(",", mysql_real_escape_string($liste_tags));
				
		//on ajoute les tags postes
		foreach ($tags as $tag) {
			trim($tag);
			if ($tag!='') {
				$GLOBALS['wiki']->InsertTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
			}			
		}
		//on copie tout de meme dans les metadonnees
		return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
	}
	elseif ($mode == 'html') {
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='') {
			$html = '<div class="BAZ_rubrique">'."\n".
						'<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
			$html .= '<div class="BAZ_texte"> ';
			$tags = explode(',',htmlentities($valeurs_fiche[$tableau_template[1]]));
			if (is_array($tags)) {
				$html .= '<ul class="liste_tags_en_ligne">'."\n";
				foreach ($tags as $tag) {
					$html .= '<li class="textboxlist-bit-box">'.$tag.'</li>'."\n";
				}	
				$html .= '</ul>'."\n";
			}
			
			$html .= '</div>'."\n".'</div>'."\n";
		}
		return $html;
	}
}



/** texte() - Ajoute un element de type texte au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function texte(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
	if ( $mode == 'saisie' )
	{
		// on prepare le html de la bulle d'aide, si elle existe
		if ($bulle_d_aide != '') {
			$bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		} else {
			$bulledaide = '';
		}
		
		//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
		//puis s'il y a une variable passee en GET,
		//enfin on prend la valeur par defaut du formulaire sinon
		if (isset($valeurs_fiche[$identifiant])) {
			$defauts = $valeurs_fiche[$identifiant];
		}
		elseif (isset($_GET[$identifiant])) {
			$defauts = stripslashes($identifiant);
		} else {
			$defauts = stripslashes($valeur_par_defaut);
		}

		//si la valeur de nb_max_car est vide, on la mets au maximum
		if ($nb_max_car == '') $nb_max_car = 255;
		
		//par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
		if ($type_input == '') $type_input = 'text';
		
		$input_html  = '<div class="form_line">'."\n".'<label>';
		$input_html .= ($obligatoire == 1) ? '<span class="required_symbol">*&nbsp;</span>' : '';
		$input_html .= $label.$bulledaide.'</label>'."\n";
		$input_html .= '<div class="form_input">'."\n";
		$input_html .= '<input type="'.$type_input.'"';
		$input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
		$input_html .= ' name="'.$identifiant.'" class="input_text" id="'.$identifiant.'"';
		$input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
		$input_html .= ($type_input == 'number' && $nb_min_car != '') ? ' min="'.$nb_min_car.'"' : '';
		$input_html .= ($type_input == 'number') ? ' max="'.$nb_max_car.'"' : '';	
		$input_html .= ($regexp != '') ? ' pattern="'.$regexp.'"' : '';
		$input_html .= ($obligatoire == 1) ? ' required="required"' : '';
		$input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";
		
		return $input_html;
	}
	elseif ( $mode == 'requete' )
	{
		return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='')
		{
			if ($tableau_template[1] == 'bf_titre')
			{
				// Le titre
				$html .= '<h1 class="BAZ_fiche_titre">'.htmlentities($valeurs_fiche[$tableau_template[1]]).'</h1>'."\n";
			}
			else
			{
				$html = '<div class="BAZ_rubrique">'."\n".
						'<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
				$html .= '<span class="BAZ_texte"> ';
				$html .= htmlentities($valeurs_fiche[$tableau_template[1]]).'</span>'."\n".'</div>'."\n";
			}
		}
		return $html;
	}
}


/** utilisateur_wikini() - Ajoute un element de type texte pour creer un utilisateur wikini au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function utilisateur_wikini(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ( $mode == 'saisie' )
	{			
		// on prepare le html de la bulle d'aide, si elle existe
		if ($tableau_template[10] != '') {
			$bulledaide = '<img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		} else {
			$bulledaide = '';
		}
			
		if (isset($valeurs_fiche['nomwiki'])) {
			$html = '<div class="form_line">
				<input type="hidden" name="nomwiki" value="'.$valeurs_fiche['nomwiki'].'" />';
			if ($GLOBALS['_BAZAR_']['nomwiki']['name']==$valeurs_fiche['nomwiki']) {
				$html .= '<a href="'.$GLOBALS['wiki']->href('','ParametresUtilisateur','').'" target="_blank">Changer son mot de passe</a>';
			}
			$html .= '</div>'."\n";
		}
		else {
			$html  = '<div class="form_line">'."\n".'<label><span class="required_symbol">*&nbsp;</span>';
			$html .= BAZ_MOT_DE_PASSE.$bulledaide.'</label>'."\n";
			$html .= '<div class="form_input">'."\n";
			$html .= '<input type="password"';
			$html .= ' name="mot_de_passe_wikini" class="input_text" id="mot_de_passe_wikini"';
			$html .= ' maxlength="'.$tableau_template[4].'" size="'.$tableau_template[3].'"';
			$html .= ' required="required"';
			$html .= '>'."\n".'</div>'."\n";	
			$html .= '</div>'."\n";
		}
		
		return $html;
	}
	elseif ( $mode == 'requete' )
	{
		if (!isset($valeurs_fiche['nomwiki'])) {
			$nomwiki = genere_nom_wiki($valeurs_fiche[$tableau_template[1]]);
			if ($GLOBALS['wiki']->LoadUser($nomwiki)) {
				$nomwiki = $nomwiki.'Bis';
			}
			$requeteinsertionuserwikini = 'INSERT INTO '.$GLOBALS['wiki']->config["table_prefix"]."users SET ".
					"signuptime = now(), ".
					"name = '".mysql_real_escape_string($nomwiki)."', ";
			if (isset($valeurs_fiche[$tableau_template[2]]) && $valeurs_fiche[$tableau_template[2]] != '') {
				$requeteinsertionuserwikini .= "email = '".mysql_real_escape_string($valeurs_fiche[$tableau_template[2]])."', ";
			}
			$requeteinsertionuserwikini .= "password = md5('".mysql_real_escape_string($valeurs_fiche['mot_de_passe_wikini'])."')";
			$resultat = $GLOBALS['wiki']->Query($requeteinsertionuserwikini) ;
			return array('nomwiki' => $nomwiki);
			
			//envoi mail nouveau mot de passe
			$lien = str_replace("/wakka.php?wiki=","",$GLOBALS['wiki']->config["base_url"]);
			$objetmail = '['.str_replace("http://","",$lien).'] Vos nouveaux identifiants sur le site '.$GLOBALS['wiki']->config["wakka_name"];
			$messagemail = "Bonjour!\n\nVotre inscription sur le site a ete finalisee, dorenavant vous pouvez vous identifier avec les informations suivantes :\n\nVotre identifiant NomWiki : ".$nomwiki."\nVotre mot de passe : ". $valeurs_fiche['mot_de_passe_wikini'] . "\n\nA tres bientot !\n\nLe webmestre";
			$headers =   'From: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
			     'Reply-To: '.BAZ_ADRESSE_MAIL_ADMIN . "\r\n" .
			     'X-Mailer: PHP/' . phpversion();
			mail($valeurs_fiche[$tableau_template[2]], remove_accents($objetmail), $messagemail, $headers);
		} 
		else {
			$requetemodificationuserwikini = 'UPDATE '.$GLOBALS['wiki']->config["table_prefix"]."users SET ".
					"email = '".mysql_real_escape_string($valeurs_fiche[$tableau_template[2]])."' WHERE name=\"".$valeurs_fiche['nomwiki']."\"";
			$resultat = $GLOBALS['wiki']->Query($requetemodificationuserwikini) ;
		}
	}
}


/** champs_cache() - Ajoute un element cache au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes options pour l'element cache
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @param    mixed   Le tableau des valeurs de la fiche
*
* @return   void
*/
function champs_cache(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ( $mode == 'saisie' )
	{
		return '<input id="'.$tableau_template[1].'" name="'.$tableau_template[1].'" type="hidden" value="'.$tableau_template[5].'" />'."\n";		
	}
	elseif ( $mode == 'requete' )
	{
		return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{

	}
}


/** champs_mail() - Ajoute un element texte formate comme un mail au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function champs_mail(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
	if ( $mode == 'saisie' )
	{
		// on prepare le html de la bulle d'aide, si elle existe
		if ($bulle_d_aide != '') {
			$bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		} else {
			$bulledaide = '';
		}
		
		//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
		//puis s'il y a une variable passee en GET,
		//enfin on prend la valeur par defaut du formulaire sinon
		if (isset($valeurs_fiche[$identifiant])) {
			$defauts = $valeurs_fiche[$identifiant];
		}
		elseif (isset($_GET[$identifiant])) {
			$defauts = stripslashes($identifiant);
		} else {
			$defauts = stripslashes($valeur_par_defaut);
		}

		//si la valeur de nb_max_car est vide, on la mets au maximum
		if ($nb_max_car == '') $nb_max_car = 255;
		
		//par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
		if ($type_input == '') $type_input = 'email';
		
		$input_html  = '<div class="form_line">'."\n".'<label>';
		$input_html .= ($obligatoire == 1) ? '<span class="required_symbol">*&nbsp;</span>' : '';
		$input_html .= $label.$bulledaide.'</label>'."\n";
		$input_html .= '<div class="form_input">'."\n";
		$input_html .= '<input type="'.$type_input.'"';
		$input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
		$input_html .= ' name="'.$identifiant.'" class="input_text" id="'.$identifiant.'"';
		$input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
		$input_html .= ($type_input == 'number' && $nb_min_car != '') ? ' min="'.$nb_min_car.'"' : '';
		$input_html .= ($type_input == 'number') ? ' max="'.$nb_max_car.'"' : '';	
		$input_html .= ($regexp != '') ? ' pattern="'.$regexp.'"' : '';
		$input_html .= ($obligatoire == 1) ? ' required="required"' : '';
		$input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";
		
		return $input_html;
	}
	elseif ( $mode == 'requete' )
	{
		return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='')
		{
			$html = '<div class="BAZ_rubrique">'."\n".
					'<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
			$html .= '<span class="BAZ_texte"><a href="mailto:'.$valeurs_fiche[$tableau_template[1]].'" class="BAZ_lien_mail">';
			$html .= $valeurs_fiche[$tableau_template[1]].'</a></span>'."\n".'</div>'."\n";
		}
		return $html;
	}
}

/** mot_de_passe() - Ajoute un element de type mot de passe au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element mot de passe
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function mot_de_passe(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ( $mode == 'saisie' )
	{
		$formtemplate->addElement('password', 'mot_de_passe', $tableau_template[2], array('size' => $tableau_template[3])) ;
		$formtemplate->addElement('password', 'mot_de_passe_repete', $tableau_template[7], array('size' => $tableau_template[3])) ;
		$formtemplate->addRule('mot_de_passe', $tableau_template[5], 'required', '', 'client') ;
		$formtemplate->addRule('mot_de_passe_repete', $tableau_template[5], 'required', '', 'client') ;
		$formtemplate->addRule(array ('mot_de_passe', 'mot_de_passe_repete'), $tableau_template[5], 'compare', '', 'client') ;
	}
	elseif ( $mode == 'requete' )
	{
		return array($tableau_template[1] => md5($valeurs_fiche['mot_de_passe'])) ;
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{

	}
}


/** textelong() - Ajoute un element de type texte long (textarea) au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte long
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function textelong(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $identifiant, $label, $nb_colonnes, $nb_lignes, $valeur_par_defaut, $longueurmax, $formatage , $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
	if ( $mode == 'saisie' )
	{
		
		if ($obligatoire == 1) $label .= '<span class="required_symbol">*&nbsp;</span>';
		if ($longueurmax != '') $options['maxlength'] = $longueurmax;
		$longueurmax = ($longueurmax ? '<span class="charsRemaining"> ('.$longueurmax.' caract&egrave;res restants)</span>' : '' );
		$bulledaide = '';
		if ($bulle_d_aide!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		
		//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
		//puis s'il y a une variable passee en GET,
		//enfin on prend la valeur par defaut du formulaire sinon
		if (isset($valeurs_fiche[$identifiant])) {
			$defauts = $valeurs_fiche[$identifiant];
		}
		elseif (isset($_GET[$identifiant])) {
			$defauts = stripslashes($_GET[$identifiant]);
		} else {
			$defauts = stripslashes($tableau_template[5]);
		}
		
		
		$formtexte = '<div class="form_line">'. "\n".'<label>'.$label.$longueurmax.$bulledaide.'</label>'.
					'	<div class="form_input">'."\n".
					'		<textarea rows="'.$nb_lignes.'" cols="'.$nb_colonnes.'" name="'.$identifiant.'" class="input_textarea" id="'.$identifiant.'" '.(($obligatoire == 1) ? 'required="required"': '').'>'."\n".$defauts.'</textarea>'."\n".
					'	</div>'."\n".
					'</div>'."\n";
		
		return $formtexte;
	}
	elseif ( $mode == 'requete' )
	{
		return array($identifiant => $valeurs_fiche[$identifiant]);
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$identifiant]) && $valeurs_fiche[$identifiant]!='')
		{
			$html = '<div class="BAZ_rubrique">'."\n".
					'<span class="BAZ_label '.$identifiant.'_rubrique">'.$label.'&nbsp;:</span>'."\n";
			$html .= '<span class="BAZ_texte '.$identifiant.'_description"> ';
			if ($formatage == 'wiki') {
				$html .= $GLOBALS['wiki']->Format($valeurs_fiche[$identifiant]);
			}
			elseif ($formatage == 'nohtml') {
				$html .= htmlentities($valeurs_fiche[$identifiant]);
			}
			else {
				$html .= nl2br($valeurs_fiche[$identifiant]);
			}
			$html .= '</span>'."\n".'</div>'."\n";
		}
		return $html;
	}
}


/** lien_internet() - Ajoute un element de type texte contenant une URL au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element texte url
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function lien_internet(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
list($type, $identifiant, $label, $nb_min_car, $nb_max_car, $valeur_par_defaut, $regexp, $type_input , $obligatoire, , $bulle_d_aide) = $tableau_template;
	if ( $mode == 'saisie' )
	{
		// on prepare le html de la bulle d'aide, si elle existe
		if ($bulle_d_aide != '') {
			$bulledaide = '<img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		} else {
			$bulledaide = '';
		}
		
		//gestion des valeurs par defaut : d'abord on regarde s'il y a une valeur a modifier,
		//puis s'il y a une variable passee en GET,
		//enfin on prend la valeur par defaut du formulaire sinon
		if (isset($valeurs_fiche[$identifiant])) {
			$defauts = $valeurs_fiche[$identifiant];
		}
		elseif (isset($_GET[$identifiant])) {
			$defauts = stripslashes($identifiant);
		} else {
			$defauts = stripslashes($valeur_par_defaut);
		}

		//si la valeur de nb_max_car est vide, on la mets au maximum
		if ($nb_max_car == '') $nb_max_car = 255;
		
		//par defaut il s'agit d'input html de type "text" (on precise si cela n'a pas ete entre)
		if ($type_input == '') $type_input = 'url';
		
		$input_html  = '<div class="form_line">'."\n".'<label>';
		$input_html .= ($obligatoire == 1) ? '<span class="required_symbol">*&nbsp;</span>' : '';
		$input_html .= $label.$bulledaide.'</label>'."\n";
		$input_html .= '<div class="form_input">'."\n";
		$input_html .= '<input type="'.$type_input.'"';
		$input_html .= ($defauts != '') ? ' value="'.$defauts.'"' : '';
		$input_html .= ' name="'.$identifiant.'" class="input_text" id="'.$identifiant.'"';
		$input_html .= ' maxlength="'.$nb_max_car.'" size="'.$nb_max_car.'"';
		$input_html .= ($type_input == 'number' && $nb_min_car != '') ? ' min="'.$nb_min_car.'"' : '';
		$input_html .= ($type_input == 'number') ? ' max="'.$nb_max_car.'"' : '';	
		$input_html .= ($regexp != '') ? ' pattern="'.$regexp.'"' : '';
		$input_html .= ($obligatoire == 1) ? ' required="required"' : '';
		$input_html .= '>'."\n".'</div>'."\n".'</div>'."\n";
		
		return $input_html;
	}
	elseif ( $mode == 'requete' )
	{
		//on supprime la valeur, si elle est restee par defaut
		if ($valeurs_fiche[$tableau_template[1]]!='http://') return array($tableau_template[1] => $valeurs_fiche[$tableau_template[1]]);
		else return;
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[1]]) && $valeurs_fiche[$tableau_template[1]]!='')
		{
			$html .= '<div class="BAZ_rubrique">'."\n".
					 '<span class="BAZ_label">'.$tableau_template[2].'&nbsp;:</span>'."\n";
			$html .= '<span class="BAZ_texte">'."\n".
					 '<a href="'.$valeurs_fiche[$tableau_template[1]].'" class="BAZ_lien" target="_blank">';
			$html .= $valeurs_fiche[$tableau_template[1]].'</a></span>'."\n".'</div>'."\n";
		}
		return $html;
	}
}

/** fichier() - Ajoute un element de type fichier au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element fichier
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function fichier(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $identifiant, $label, $taille_maxi, $taille_maxi2, $hauteur, $largeur, $alignement, $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
	if ($mode == 'saisie')
	{
		//AJOUTER DES FICHIERS JOINTS
		$html= '';
		if ($bulle_d_aide!='') $label = $label.' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		if (isset($valeurs_fiche[$type.$identifiant])) {
			$lien_supprimer = $GLOBALS['wiki']->href('','','action='.$_GET['action'].'&amp;id_fiche='.$GLOBALS['_BAZAR_']["id_fiche"].'&amp;fichier=1');
			
			$html .= $valeurs_fiche[$type.$identifiant]."\n".
			'<a href="'.$lien_supprimer.'" onclick="javascript:return confirm(\''.BAZ_CONFIRMATION_SUPPRESSION_FICHIER.'\');" >'.BAZ_SUPPRIMER.'</a><br />'."\n";
		}
	
		$html .= '<div class="form_line">
				<label for="'.$type.$identifiant.'">';
		$html .= (isset($obligatoire) && ($obligatoire==1)) ? '<span class="required_symbol">*&nbsp;</span>' : '';
		$html .= $label.' :</label>';
		$html .= '<div class="form_input"> 
					<input type="file" id="'.$type.$identifiant.'"';
		$html .= (isset($obligatoire) && ($obligatoire==1)) ? ' required="required"' : '';
		$html .= '>
			</div>
		</div>';
		return $html;
	}
	elseif ( $mode == 'requete' )
	{
			if (isset($_FILES[$type.$identifiant]['name']) && $_FILES[$type.$identifiant]['name']!='') {
				//on enleve les accents sur les noms de fichiers, et les espaces
				$nomfichier = preg_replace("/&([a-z])[a-z]+;/i","$1", htmlentities($identifiant.'_'.$_FILES[$type.$identifiant]['name']));
				$nomfichier = str_replace(' ', '_', $nomfichier);
				$chemin_destination = BAZ_CHEMIN_UPLOAD.$nomfichier;
				//verification de la presence de ce fichier
				if (!file_exists($chemin_destination)) {
					move_uploaded_file($_FILES[$type.$identifiant]['tmp_name'], $chemin_destination);
					chmod ($chemin_destination, 0755);
				}
				else echo 'fichier deja existant<br />';
				return array($type.$identifiant => $nomfichier);
			}
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant]!='')
		{
			$html = '<div class="BAZ_fichier">T&eacute;l&eacute;charger le fichier : <a href="'.BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant].'">'.$valeurs_fiche[$type.$identifiant].'</a>'."\n";
		}
		if ($html!='') $html .= '</div>'."\n";
		return $html;
	}
}

/** image() - Ajoute un element de type image au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element image
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function image(&$formtemplate, $tableau_template, $mode, $valeurs_fiche) {
	list($type, $identifiant, $label, $hauteur_vignette, $largeur_vignette, $hauteur_image, $largeur_image, $class, $obligatoire, $apparait_recherche, $bulle_d_aide) = $tableau_template;
	
	if ( $mode == 'saisie') {
		//on verifie qu'il ne faut supprimer l'image
		if (isset($_GET['suppr_image']) && $valeurs_fiche[$type.$identifiant]==$_GET['suppr_image']) {
			//on efface le fichier s'il existe
			if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {
				unlink(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant]);
			}
			
			//on efface une entrée de la base de donnees
			 unset($valeurs_fiche[$type.$identifiant]);
			 $valeur = $valeurs_fiche;
			 $valeur['date_maj_fiche'] = date( 'Y-m-d H:i:s', time() );
			 $valeur['id_fiche'] = $GLOBALS['_BAZAR_']['id_fiche'];
			 $valeur = json_encode(array_map("utf8_encode", $valeur));
			 //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
			$GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);
			
			//on affiche les infos sur l'effacement du fichier, et on reinitialise la variable pour le fichier pour faire apparaitre le formulaire d'ajout par la suite
			echo '<div class="info_box">'.BAZ_FICHIER.$valeurs_fiche[$type.$identifiant].BAZ_A_ETE_EFFACE.'</div>'."\n";
			$valeurs_fiche[$type.$identifiant] = '';
		}
		
		if ($bulle_d_aide!='') $labelbulle = $label.' <img class="tooltip_aide" title="'.htmlentities($bulle_d_aide).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		
		//cas ou il y a une image dans la base de donnees
		if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant] != '') {			
			
			//il y a bien le fichier image, on affiche l'image, avec possibilite de la supprimer ou de la modifier
			if (file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant])) {
				
				require_once BAZ_CHEMIN.'libs'.DIRECTORY_SEPARATOR.'HTML/QuickForm/html.php';
				$formtemplate->addElement(new HTML_QuickForm_html("\n".'<fieldset class="bazar_fieldset">'."\n".'<legend>'.$labelbulle.'</legend>'."\n")) ;
				
				$lien_supprimer=clone($GLOBALS['_BAZAR_']['url']);
				$lien_supprimer->addQueryString('action', $_GET['action']);
				$lien_supprimer->addQueryString('id_fiche', $GLOBALS['_BAZAR_']["id_fiche"]);
				$lien_supprimer->addQueryString('suppr_image', $valeurs_fiche[$type.$identifiant]);
				
				$html_image = afficher_image($valeurs_fiche[$type.$identifiant], $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image);
				$lien_supprimer_image .= '<a class="BAZ_lien_supprimer" href="'.str_replace('&', '&amp;', $lien_supprimer->getURL()).'" onclick="javascript:return confirm(\''.
				BAZ_CONFIRMATION_SUPPRESSION_IMAGE.'\');" >'.BAZ_SUPPRIMER_IMAGE.'</a>'."\n";
				if ($html_image!='') $formtemplate->addElement('html', $html_image) ;
				$formtemplate->addElement('file', $type.$identifiant, $lien_supprimer_image.BAZ_MODIFIER_IMAGE) ;
				$formtemplate->addElement(new HTML_QuickForm_html("\n".'</fieldset>'."\n")) ;
			}
			
			//le fichier image n'existe pas, du coup on efface l'entree dans la base de donnees
			else {
				echo '<div class="BAZ_error">'.BAZ_FICHIER.$valeurs_fiche[$type.$identifiant].BAZ_FICHIER_IMAGE_INEXISTANT.'</div>'."\n";
				//on efface une entree de la base de donnees
				 unset($valeurs_fiche[$type.$identifiant]);
				 $valeur = $valeurs_fiche;
				 $valeur['date_maj_fiche'] = date( 'Y-m-d H:i:s', time() );
				 $valeur['id_fiche'] = $GLOBALS['_BAZAR_']['id_fiche'];
				 $valeur = json_encode(array_map("utf8_encode", $valeur));
				 //on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
				$GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);
			}
		} 
		//cas ou il n'y a pas d'image dans la base de donnees, on affiche le formulaire d'envoi d'image
		else {
			$html = '<div class="form_line">
				<div class="formulaire_label">'.$label.' :</div>
				<div class="form_input"> 
					<input type="file" name="'.$type.$identifiant.'"';
			$html .= (isset($obligatoire) && ($obligatoire==1)) ? ' required="required"' : '';
			$html .= '>
				</div>
			</div>';
			return $html;
		}
	}
	elseif ( $mode == 'requete' ) {
		if (isset($_FILES[$type.$identifiant]['name']) && $_FILES[$type.$identifiant]['name']!='') {
							
			//on enleve les accents sur les noms de fichiers, et les espaces
			$nomimage = preg_replace("/&([a-z])[a-z]+;/i","$1", htmlentities($identifiant.$_FILES[$type.$identifiant]['name']));
			$nomimage = str_replace(' ', '_', $nomimage);
			$chemin_destination = BAZ_CHEMIN_UPLOAD.$nomimage;
			//verification de la presence de ce fichier
			if (!file_exists($chemin_destination)) {
				move_uploaded_file($_FILES[$type.$identifiant]['tmp_name'], $chemin_destination);
				chmod ($chemin_destination, 0755);
				//generation des vignettes
				if ($hauteur_vignette!='' && $largeur_vignette!='' && !file_exists('cache/vignette_'.$nomimage)) {
					$adr_img = redimensionner_image($chemin_destination, 'cache/vignette_'.$nomimage, $largeur_vignette, $hauteur_vignette);
				}
				//generation des images
				if ($hauteur_image!='' && $largeur_image!='' && !file_exists('cache/image_'.'_'.$nomimage)) {
					$adr_img = redimensionner_image($chemin_destination, 'cache/image_'.$nomimage, $largeur_image, $hauteur_image);
				}
			}
			else {
				echo '<div class="BAZ_error">L\'image '.$nomimage.' existait d&eacute;ja, elle n\'a pas &eacute;t&eacute; remplac&eacute;e.</div>';
			}
			return array($type.$identifiant => $nomimage);
		}
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{
		if (isset($valeurs_fiche[$type.$identifiant]) && $valeurs_fiche[$type.$identifiant]!='' && file_exists(BAZ_CHEMIN_UPLOAD.$valeurs_fiche[$type.$identifiant]) )
		{
			return afficher_image($valeurs_fiche[$type.$identifiant], $label, $class, $largeur_vignette, $hauteur_vignette, $largeur_image, $hauteur_image);
		}
	}
}

/** labelhtml() - Ajoute du texte HTML au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour le texte HTML
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function labelhtml(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $texte_saisie, $texte_recherche, $texte_fiche) = $tableau_template;

	if ( $mode == 'saisie' )
	{
		return $texte_saisie."\n";
	}
	elseif ($mode == 'formulaire_recherche')
	{
		return $texte_recherche."\n";
	}
	elseif ($mode == 'html')
	{
		return $texte_fiche."\n";
	}
}

/** titre() - Action qui camouffle le titre et le genre a partir d'autres champs au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour le texte HTML
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function titre(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $template) = $tableau_template;

	if ( $mode == 'saisie' )
	{
		return '<input id="bf_titre" name="bf_titre" type="hidden" value="'.$template.'" />'."\n";
	}
	elseif ( $mode == 'requete' )
	{
		preg_match_all  ('#{{(.*)}}#U'  , $_POST['bf_titre']  , $matches);
		$tab = array();
		$valeurs_fiche['bf_titre'] = $_POST['bf_titre'];
		foreach ($matches[1] as $var) {
			if (isset($_POST[$var])) {
				//pour une listefiche ou une checkboxfiche on cherche le titre de la fiche
				if ( preg_match('#^listefiche#',$var)!=false || preg_match('#^checkboxfiche#',$var)!=false ) {
					$tab_fiche = baz_valeurs_fiche($_POST[$var]);
					$valeurs_fiche['bf_titre'] = str_replace('{{'.$var.'}}', ($tab_fiche['bf_titre']!=null) ? $tab_fiche['bf_titre'] : '', $valeurs_fiche['bf_titre']);
				}			
				else {
					$valeurs_fiche['bf_titre'] = str_replace('{{'.$var.'}}', $_POST[$var], $valeurs_fiche['bf_titre']);
				}		
			}
		}
		return array('bf_titre' => $valeurs_fiche['bf_titre']);
	}
	elseif ($mode == 'html')
	{
		// Le titre
		return '<h1 class="BAZ_fiche_titre">'.htmlentities($valeurs_fiche['bf_titre']).'</h1>'."\n";
	}
	elseif ($mode == 'formulaire_recherche')
	{
		return;
	}
}

/** carte_google() - Ajoute un element de carte google au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour la carte google
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function carte_google(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	list($type, $lat, $lon, $classe, $obligatoire) = $tableau_template;
	
	if ( $mode == 'saisie' )
	{
		if (isset($valeurs_fiche['carte_google'])) {
			$tab=explode('|', $valeurs_fiche['carte_google']);
			if (count($tab)>1) {
				$defauts = array( $lat => $tab[0], $lon => $tab[1] );
			}
		}

		$html_bouton = '<div class="titre_carte_google">'.METTRE_POINT.'</div>';

		$html_bouton .= '<input class="btn_adresse" onclick="showAddress();" name="chercher_sur_carte" value="'.VERIFIER_MON_ADRESSE.'" type="button" />
	<input class="btn_client" onclick="showClientAddress();" name="chercher_client" value="'.VERIFIER_MON_ADRESSE_CLIENT.'" type="button" />';

		$scriptgoogle = '//-----------------------------------------------------------------------------------------------------------
	//--------------------TODO : ATTENTION CODE FACTORISABLE-----------------------------------------------------
	//-----------------------------------------------------------------------------------------------------------
	var geocoder;
	var map;
	var marker;
	var infowindow;

	function initialize() {
		geocoder = new google.maps.Geocoder();
		var myLatlng = new google.maps.LatLng('.BAZ_GOOGLE_CENTRE_LAT.', '.BAZ_GOOGLE_CENTRE_LON.');
		var myOptions = {
		  zoom: '.BAZ_GOOGLE_ALTITUDE.',
		  center: myLatlng,
		  mapTypeId: google.maps.MapTypeId.'.BAZ_TYPE_CARTO.',
		  navigationControl: '.BAZ_AFFICHER_NAVIGATION.',
		  navigationControlOptions: {style: google.maps.NavigationControlStyle.'.BAZ_STYLE_NAVIGATION.'},
		  mapTypeControl: '.BAZ_AFFICHER_CHOIX_CARTE.',
		  mapTypeControlOptions: {style: google.maps.MapTypeControlStyle.'.BAZ_STYLE_CHOIX_CARTE.'},
		  scaleControl: '.BAZ_AFFICHER_ECHELLE.' ,
		  scrollwheel: '.BAZ_PERMETTRE_ZOOM_MOLETTE.'
		}
		map = new google.maps.Map(document.getElementById("map"), myOptions);

		//on pose un point si les coordonnees existent deja (cas d\'une modification de fiche)
		if (document.getElementById("latitude") && document.getElementById("latitude").value != \'\' &&
			document.getElementById("longitude") && document.getElementById("longitude").value != \'\' ) {
			var lat = document.getElementById("latitude").value;
			var lon = document.getElementById("longitude").value;
			latlngclient = new google.maps.LatLng(lat,lon);
			map.setCenter(latlngclient);
			infowindow = new google.maps.InfoWindow({
				content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
				maxWidth: 250
			});
			//image du marqueur
			var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
			//taille, point d\'origine, point d\'arrivee de l\'image
			new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

			//ombre du marqueur
			var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
			// taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
			new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

			marker = new google.maps.Marker({
				position: latlngclient,
				map: map,
				icon: image,
				shadow: shadow,
				title: \'Votre emplacement\',
				draggable: true
			});
			infowindow.open(map,marker);
			google.maps.event.addListener(marker, \'click\', function() {
			  infowindow.open(map,marker);
			});
			google.maps.event.addListener(marker, "dragend", function () {
				var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
				var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
				map.setCenter(marker.getPosition());
			});
		}
	};

	function showClientAddress(){
		// If ClientLocation was filled in by the loader, use that info instead
		if (google.loader.ClientLocation) {
		  latlngclient = new google.maps.LatLng(google.loader.ClientLocation.latitude, google.loader.ClientLocation.longitude);
		  if(infowindow) {
			infowindow.close();
		  }
		  if(marker) {
			marker.setMap(null);
		  }
		  map.setCenter(latlngclient);
			var lat = document.getElementById("latitude");lat.value = map.getCenter().lat();
			var lon = document.getElementById("longitude");lon.value = map.getCenter().lng();

			infowindow = new google.maps.InfoWindow({
				content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
				maxWidth: 250
			});
			//image du marqueur
			var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
			//taille, point d\'origine, point d\'arrivee de l\'image
			new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

			//ombre du marqueur
			var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
			// taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
			new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
			new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

			marker = new google.maps.Marker({
				position: latlngclient,
				map: map,
				icon: image,
				shadow: shadow,
				title: \'Votre emplacement\',
				draggable: true
			});
			infowindow.open(map,marker);
			google.maps.event.addListener(marker, \'click\', function() {
			  infowindow.open(map,marker);
			});
			google.maps.event.addListener(marker, "dragend", function () {
				var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
				var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
				map.setCenter(marker.getPosition());
			});
		}
		else {alert("Localisation par votre acces Internet impossible..");}
	};

	function showAddress() {

	  if (document.getElementById("bf_adresse1")) 	var adress_1 = document.getElementById("bf_adresse1").value ; else var adress_1 = "";
	  if (document.getElementById("bf_adresse2")) 	var adress_2 = document.getElementById("bf_adresse2").value ; else var adress_2 = "";
	  if (document.getElementById("bf_ville")) 	var ville = document.getElementById("bf_ville").value ; else var ville = "";
	  if (document.getElementById("bf_code_postal")) var cp = document.getElementById("bf_code_postal").value ; else var cp = "";
	  if (document.getElementById("bf_ce_pays")) var pays = document.getElementById("bf_ce_pays").value ; else 
	  if (document.getElementById("liste3"))  {
		   var selectIndex=document.getElementById("liste3").selectedIndex;
		   var pays = document.getElementById("liste3").options[selectIndex].text ;
	  } else {
		  var pays = "";
	  };



	  var address = adress_1 + \' \' + adress_2 + \' \'  + cp + \' \' + ville + \' \' +pays ;
	  address = address.replace(/\\("|\'|\\)/g, " ");
	  if (geocoder) {
		  geocoder.geocode( { \'address\': address}, function(results, status) {
			if (status == google.maps.GeocoderStatus.OK) {
			  if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {
				if(infowindow) {
				  infowindow.close();
				}
				if(marker) {
					marker.setMap(null);
				}
				map.setCenter(results[0].geometry.location);
				var lat = document.getElementById("latitude");lat.value = map.getCenter().lat();
				var lon = document.getElementById("longitude");lon.value = map.getCenter().lng();

				infowindow = new google.maps.InfoWindow({
					content: "<h4>Votre emplacement<\/h4>'.TEXTE_POINT_DEPLACABLE.'",
					maxWidth: 250
				});
				//image du marqueur
				var image = new google.maps.MarkerImage(\''.BAZ_IMAGE_MARQUEUR.'\',
				//taille, point d\'origine, point d\'arrivee de l\'image
				new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_MARQUEUR.'),
				new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_MARQUEUR.'),
				new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_MARQUEUR.'));

				//ombre du marqueur
				var shadow = new google.maps.MarkerImage(\''.BAZ_IMAGE_OMBRE_MARQUEUR.'\',
				// taille, point d\'origine, point d\'arrivee de l\'image de l\'ombre
				new google.maps.Size('.BAZ_DIMENSIONS_IMAGE_OMBRE_MARQUEUR.'),
				new google.maps.Point('.BAZ_COORD_ORIGINE_IMAGE_OMBRE_MARQUEUR.'),
				new google.maps.Point('.BAZ_COORD_ARRIVEE_IMAGE_OMBRE_MARQUEUR.'));

				marker = new google.maps.Marker({
					position: results[0].geometry.location,
					map: map,
					icon: image,
					shadow: shadow,
					title: \'Votre emplacement\',
					draggable: true
				});
				infowindow.open(map,marker);
				google.maps.event.addListener(marker, \'click\', function() {
				  infowindow.open(map,marker);
				});
				google.maps.event.addListener(marker, "dragend", function () {
					var lat = document.getElementById("latitude");lat.value = marker.getPosition().lat();
					var lon = document.getElementById("longitude");lon.value = marker.getPosition().lng();
					map.setCenter(marker.getPosition());
				});
			  } else {
				alert("Pas de r?sultats pour cette adresse: " + address);
			  }
			} else {
			  alert("Pas de r?sultats pour la raison suivante: " + status + ", rechargez la page.");
			}
		  });
		}
	  };';
	  if ( defined('BAZ_JS_INIT_MAP') && BAZ_JS_INIT_MAP != '' && file_exists(BAZ_JS_INIT_MAP) ) {
		$handle = fopen(BAZ_JS_INIT_MAP, "r");
		$scriptgoogle .= fread($handle, filesize(BAZ_JS_INIT_MAP));
		fclose($handle);
		$scriptgoogle .= 'var poly = createPolygon( Coords, "#002F0F");
		poly.setMap(map);
		
		';
	};		
	  $script = '<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>'."\n".
	  			'<script type="text/javascript" src="http://www.google.com/jsapi"></script>'."\n".
	  			'<script type="text/javascript">
				//<![CDATA[
				'.$scriptgoogle.'
				//]]>
				</script>';
		$formtemplate = $html_bouton;
		$formtemplate .= '<div class="coordonnees_google">'."\n".
						'	<label>'.((isset($obligatoire) && $obligatoire==1) ? '<span class="required_symbol">*&nbsp;</span>' : '').LATITUDE.'</label>'."\n".
						'	<input type="text" name="bf_latitude" readonly="readonly" size="6" id="latitude" '.
								((isset($obligatoire) && $obligatoire==1) ? 'required="required"' : '').
								((isset($defaults[$lat])) ? 'value="'.$defaults[$lat].'"' : '').'>'."\n".
						'	<label>'.((isset($obligatoire) && $obligatoire==1) ? '<span class="required_symbol">*&nbsp;</span>' : '').LONGITUDE.'</label>'."\n".
						'	<input type="text" name="bf_longitude" readonly="readonly" size="6" id="longitude" '.
								((isset($obligatoire) && $obligatoire==1) ? 'required="required"' : '').
								((isset($defaults[$lon])) ? 'value="'.$defaults[$lon].'"' : '').'>'."\n".
						'</div>'."\n";
		$formtemplate .= $script.'<div id="map" style="width: '.BAZ_GOOGLE_IMAGE_LARGEUR.'; height: '.BAZ_GOOGLE_IMAGE_HAUTEUR.';"></div>';
	
		return $formtemplate;
    }
	elseif ( $mode == 'requete' )
	{
		return array('carte_google' => $valeurs_fiche[$lat].'|'.$valeurs_fiche[$lon]);
	}
	elseif ($mode == 'recherche')
	{

	}
	elseif ($mode == 'html')
	{

	}

}

/** listefiche() - Ajoute un element de type liste deroulante correspondant a un autre type de fiche au formulaire
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour l'element liste
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @return   void
*/
function listefiche(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ($mode=='saisie') {
		$bulledaide = '';
		if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
		$val_type = baz_valeurs_formulaire($tableau_template[1]);
		$tab_result = baz_requete_recherche_fiches('', 'alphabetique', $tableau_template[1], $val_type["bn_type_fiche"]);
		$select[0] = BAZ_CHOISIR;
		foreach ($tab_result as $fiche) {
			$valeurs_fiche = json_decode($fiche[0], true);
			$valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
			$select[$valeurs_fiche['id_fiche']] = $valeurs_fiche['bf_titre'] ;
		}

		$option = array('id' => $tableau_template[0].$tableau_template[1].$tableau_template[6]);
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			$def = $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]];
		}
		else
		{
			$def = $tableau_template[5];
		}
		require_once 'HTML/QuickForm/select.php';
		$select= new HTML_QuickForm_select($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].$bulledaide, $select, $option);
		if ($tableau_template[4] != '') $select->setSize($tableau_template[4]);
		$select->setMultiple(0);
		$select->setValue($def);
		$formtemplate->addElement($select) ;

		if (isset($tableau_template[8]) && $tableau_template[8]==1 && $resultat->numRows()>0)
		{
			$formtemplate->addRule($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].' obligatoire', 'required', '', 'client') ;
		}
	}
	elseif ($mode == 'requete')
	{
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!=0))
		{
			return array($tableau_template[0].$tableau_template[1].$tableau_template[6] => $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
		}
	}
	elseif ($mode == 'formulaire_recherche')
	{
		if ($tableau_template[9]==1)
		{
			$tab_result = baz_requete_recherche_fiches('', $tri = 'alphabetique', $tableau_template[1], '');
			$select[0] = BAZ_INDIFFERENT;
			foreach ($tab_result as $fiche) {
				$valeurs_fiche = json_decode($fiche[0], true);
				$valeurs_fiche = array_map('utf8_decode', $valeurs_fiche);
				$select[$valeurs_fiche['id_fiche']] = $valeurs_fiche['bf_titre'] ;
			}
			$option = array('id' => $tableau_template[0].$tableau_template[1].$tableau_template[6]);
			require_once 'HTML/QuickForm/select.php';
			$select= new HTML_QuickForm_select($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2], $select, $option);
			if ($tableau_template[4] != '') $select->setSize($tableau_template[4]);
			$select->setMultiple(0);
			$formtemplate->addElement($select) ;
		}
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			
			if ($tableau_template[3] == 'fiche') {
				$html = baz_voir_fiche(0, $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
			} else {
				$html = '<div class="BAZ_rubrique  BAZ_rubrique_'.$GLOBALS['_BAZAR_']['class'].'">'."\n".
						'<span class="BAZ_label '.$tableau_template[2].'_rubrique">'.$tableau_template[2].'&nbsp;:</span>'."\n";
				$html .= '<span class="BAZ_texte BAZ_texte_'.$GLOBALS['_BAZAR_']['class'].' '.$tableau_template[2].'_description">';
				$url_voirfiche = clone($GLOBALS['_BAZAR_']['url']);
				$url_voirfiche->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
				$url_voirfiche->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
				$url_voirfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
				$url_voirfiche->addQueryString('id_fiche', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]);
				$html .= '<a href="'.str_replace('&', '&amp;', $url_voirfiche->getUrl()).'" class="voir_fiche ouvrir_overlay" title="Voir la fiche '.$res[0].'" rel="#overlay">'.$res[0].'</a></span>'."\n".'</div>'."\n";
			}
		}
		return $html;
	}
} //fin listefiche()


/** checkboxfiche() - permet d'aller saisir et modifier un autre type de fiche
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour le texte HTML
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @param    mixed	Tableau des valeurs par defauts (pour modification)
*
* @return   void
*/
function checkboxfiche(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if ( $mode == 'saisie' )
	{
		if (isset($GLOBALS['_BAZAR_']['id_fiche']) && $GLOBALS['_BAZAR_']['id_fiche']!='') 
		{
			$html  = '';
			$bulledaide = '';
			if (isset($tableau_template[10]) && $tableau_template[10]!='') $bulledaide = ' <img class="tooltip_aide" title="'.htmlentities($tableau_template[10]).'" src="tools/bazar/presentation/images/aide.png" width="16" height="16" alt="image aide" />';
			//TODO: gestion multilinguisme
			$requete  = 'SELECT bf_id_fiche, bf_titre FROM '.BAZ_PREFIXE.'fiche WHERE bf_ce_nature='.$tableau_template[1];
			
			//on affiche que les fiches saisie par un utilisateur donne
			if (isset($tableau_template[7]) && $tableau_template[7]==1) $requete .= ' AND bf_ce_utilisateur="'.$GLOBALS['_BAZAR_']['nomwiki']['name'].'"';
			
			//on classe par ordre alphabetique
			$requete .= ' ORDER BY bf_titre';
			
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
			if (DB::isError ($resultat))
			{
				return ($resultat->getMessage().$resultat->getDebugInfo()) ;
			}
			require_once 'HTML/QuickForm/checkbox.php' ;
			$i=0;
			$optioncheckbox = array('class' => 'element_checkbox');

			//valeurs par defauts
			if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) $tab = explode( ',', $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]] );
			else $tab = explode( ',', $tableau_template[5] );

			while ($ligne = $resultat->fetchRow()) {
				if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
				$url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
				$url_checkboxfiche->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
				$url_checkboxfiche->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
				$url_checkboxfiche->addQueryString('id_fiche', $ligne[0] );
				$url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
				$checkbox[$i]= & HTML_QuickForm::createElement('checkbox', $ligne[0], $tab_chkbox, '<a class="voir_fiche ouvrir_overlay" rel="#overlay" href="'.str_replace('&','&amp;',$url_checkboxfiche->getURL()).'">'.$ligne[1].'</a>', $optioncheckbox) ;
				$url_checkboxfiche->removeQueryString(BAZ_VARIABLE_VOIR);
				$url_checkboxfiche->removeQueryString(BAZ_VARIABLE_ACTION);
				$url_checkboxfiche->removeQueryString('id_fiche');
				$url_checkboxfiche->removeQueryString('wiki');
				if (in_array($ligne[0],$tab)) {
						$defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$ligne[0].']']=true;
				} else $defaultValues[$tableau_template[0].$tableau_template[1].$tableau_template[6].'['.$ligne[0].']']=false;
				$i++;
			}

			if (is_array($checkbox))
			{
				$formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[4], "\n");
				if (isset($tableau_template[8]) && $tableau_template[8]==1) {
					$formtemplate->addGroupRule($tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[4].' obligatoire', 'required', null, 1, 'client');
				}
				$formtemplate->setDefaults($defaultValues);
			}
			//ajout lien nouvelle saisie
			$url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
			$url_checkboxfiche->removeQueryString('id_fiche');
			$url_checkboxfiche->addQueryString('vue', BAZ_VOIR_SAISIR);
			$url_checkboxfiche->addQueryString('action', BAZ_ACTION_NOUVEAU);
			$url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
			$url_checkboxfiche->addQueryString('id_typeannonce', $tableau_template[1]);
			$url_checkboxfiche->addQueryString('ce_fiche_liee', $_GET['id_fiche']);	
			$html .= '<a class="ajout_fiche ouvrir_overlay" href="'.str_replace('&', '&amp;', $url_checkboxfiche->getUrl()).'" rel="#overlay" title="'.htmlentities($tableau_template[2]).'">'.$tableau_template[2].'</a>'."\n";
			$formtemplate->addElement('html', $html);
		} else {
			$formtemplate->addElement('html', '<div class="info_box">'.$tableau_template[3].'</div>');
		}
	}
	elseif ( $mode == 'requete' )
	{
		//on supprime les anciennes valeurs de la table '.BAZ_PREFIXE.'fiche_valeur_texte
		$requetesuppression='DELETE FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_ce_fiche="'.$GLOBALS['_BAZAR_']['id_fiche'].'" AND bfvt_id_element_form="'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'"';
		$resultat = $GLOBALS['_BAZAR_']['db']->query($requetesuppression) ;
		if (DB::isError($resultat))
		{
				echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
		}
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && ($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!=0))
		{
			//on insere les nouvelles valeurs
			$requeteinsertion='INSERT INTO '.BAZ_PREFIXE.'fiche_valeur_texte (bfvt_ce_fiche, bfvt_id_element_form, bfvt_texte) VALUES ';
			//pour les checkbox, les differentes valeurs sont dans un tableau
			if (is_array($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
				$nb=0;
				while (list($cle, $val) = each($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]])) {
					if ($nb>0) $requeteinsertion .= ', ';
					$requeteinsertion .= '("'.$GLOBALS['_BAZAR_']['id_fiche'].'", "'.$tableau_template[0].$tableau_template[1].$tableau_template[6].'", "'.$cle.'") ';
					$nb++;
				}
			}
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requeteinsertion) ;
			if (DB::isError($resultat)) {
				echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
			}
		}
	}
	elseif ($mode == 'formulaire_recherche')
	{
		if ($tableau_template[9]==1)
		{
			$requete =  'SELECT * FROM '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste='.$tableau_template[1].
						' AND blv_ce_i18n like "'.$GLOBALS['_BAZAR_']['langue'].'%" ORDER BY blv_label';
			$resultat = & $GLOBALS['_BAZAR_']['db'] -> query($requete) ;
			if (DB::isError ($resultat)) {
				echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
			}
			require_once 'HTML/QuickForm/checkbox.php' ;
			$i=0;
			$optioncheckbox = array('class' => 'element_checkbox');

			while ($ligne = $resultat->fetchRow()) {
				if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
				$checkbox[$i]= & HTML_QuickForm::createElement($tableau_template[0], $ligne[1], $tab_chkbox, $ligne[2], $optioncheckbox) ;
				$i++;
			}

			$squelette_checkbox =& $formtemplate->defaultRenderer();
			$squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
													'<!-- BEGIN required --><span class="required_symbol">&nbsp;*</span><!-- END required -->'."\n".
													'</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
			$squelette_checkbox->setGroupElementTemplate( "\n".'<div class="bazar_checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
			$formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].$bulledaide, "\n");
		}
	}
	elseif ($mode == 'html')
	{
		$html = '';
		if (isset($valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]) && $valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]]!='')
		{
			$requete  = 'SELECT bf_id_fiche, bf_titre FROM '.BAZ_PREFIXE.'fiche WHERE bf_id_fiche IN ('.$valeurs_fiche[$tableau_template[0].$tableau_template[1].$tableau_template[6]].') AND bf_ce_nature='.$tableau_template[1];
			
			//on classe par ordre alphabetique
			$requete .= ' ORDER BY bf_titre';
			
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
			if (DB::isError ($resultat))
			{
				return ($resultat->getMessage().$resultat->getDebugInfo()) ;
			}
			$i=0;
			
			while ($ligne = $resultat->fetchRow()) {
				$url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
				$url_checkboxfiche->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
				$url_checkboxfiche->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
				$url_checkboxfiche->addQueryString('id_fiche', $ligne[0] );
				$url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
				$checkbox[$i]= '<a class="voir_fiche ouvrir_overlay" rel="#overlay" href="'.str_replace('&','&amp;',$url_checkboxfiche->getURL()).'">'.$ligne[1].'</a>';
				$url_checkboxfiche->removeQueryString(BAZ_VARIABLE_VOIR);
				$url_checkboxfiche->removeQueryString(BAZ_VARIABLE_ACTION);
				$url_checkboxfiche->removeQueryString('id_fiche');
				$url_checkboxfiche->removeQueryString('wiki');
				$i++;
			}

			if (is_array($checkbox))
			{
				$html .= '<ul>'."\n";
				foreach($checkbox as $lien_fiche)
				{
					$html .= '<li>'.$lien_fiche.'</li>'."\n";
				}
				$html .= '</ul>'."\n";
			}
		}

		return $html;
	}
}

/** listefiches() - permet d'aller saisir et modifier un autre type de fiche
*
* @param    mixed   L'objet QuickForm du formulaire
* @param    mixed   Le tableau des valeurs des differentes option pour le texte HTML
* @param    string  Type d'action pour le formulaire : saisie, modification, vue,... saisie par defaut
* @param    mixed	Tableau des valeurs par defauts (pour modification)
*
* @return   void
*/
function listefiches(&$formtemplate, $tableau_template, $mode, $valeurs_fiche)
{
	if (!isset($tableau_template[1])) 
	{
		return $GLOBALS['wiki']->Format('//Erreur sur listefiches : pas d\'identifiant de type de fiche passe...//');
	}
	if (isset($tableau_template[2]) && $tableau_template[2] != '' ) 
	{
		$query = $tableau_template[2].'|listefiche'.$valeurs_fiche['id_typeannonce'].'='.$valeurs_fiche['id_fiche'];
	}
	elseif (isset($valeurs_fiche) && $valeurs_fiche != '')
	{
		$query = 'listefiche'.$valeurs_fiche['id_typeannonce'].'='.$valeurs_fiche['id_fiche'];
	}
	if (isset($tableau_template[3])) 
	{
		$ordre = $tableau_template[3];
	}
	else 
	{
		$ordre = 'alphabetique';
	}
	
	if (isset($valeurs_fiche['id_fiche']) && $mode == 'saisie' )
	{
		$actionbazarliste = '{{bazarliste idtypeannonce="'.$tableau_template[1].'" query="'.$query.'" ordre="'.$ordre.'"}}';
		$html = $GLOBALS['wiki']->Format($actionbazarliste);	
		//ajout lien nouvelle saisie
		$url_checkboxfiche = clone($GLOBALS['_BAZAR_']['url']);
		$url_checkboxfiche->removeQueryString('id_fiche');
		$url_checkboxfiche->addQueryString('vue', BAZ_VOIR_SAISIR);
		$url_checkboxfiche->addQueryString('action', BAZ_ACTION_NOUVEAU);
		$url_checkboxfiche->addQueryString('wiki', $_GET['wiki'].'/iframe');
		$url_checkboxfiche->addQueryString('id_typeannonce', $tableau_template[1]);
		$url_checkboxfiche->addQueryString('ce_fiche_liee', $_GET['id_fiche']);	
		$html .= '<a class="ajout_fiche ouvrir_overlay" href="'.str_replace('&', '&amp;', $url_checkboxfiche->getUrl()).'" rel="#overlay" title="'.htmlentities($tableau_template[4]).'">'.$tableau_template[4].'</a>'."\n";
		$formtemplate->addElement('html', $html);
	}
	elseif ( $mode == 'requete' )
	{
	}
	elseif ($mode == 'formulaire_recherche')
	{
		if ($tableau_template[9]==1)
		{
			$requete =  'SELECT * FROM '.BAZ_PREFIXE.'liste_valeurs WHERE blv_ce_liste='.$tableau_template[1].
						' AND blv_ce_i18n like "'.$GLOBALS['_BAZAR_']['langue'].'%" ORDER BY blv_label';
			$resultat = & $GLOBALS['_BAZAR_']['db'] -> query($requete) ;
			if (DB::isError ($resultat)) {
				echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
			}
			require_once 'HTML/QuickForm/checkbox.php' ;
			$i=0;
			$optioncheckbox = array('class' => 'element_checkbox');

			while ($ligne = $resultat->fetchRow()) {
				if ($i==0) $tab_chkbox=$tableau_template[2] ; else $tab_chkbox='&nbsp;';
				$checkbox[$i]= & HTML_QuickForm::createElement($tableau_template[0], $ligne[1], $tab_chkbox, $ligne[2], $optioncheckbox) ;
				$i++;
			}

			$squelette_checkbox =& $formtemplate->defaultRenderer();
			$squelette_checkbox->setElementTemplate( '<fieldset class="bazar_fieldset">'."\n".'<legend>{label}'.
													'<!-- BEGIN required --><span class="required_symbol">&nbsp;*</span><!-- END required -->'."\n".
													'</legend>'."\n".'{element}'."\n".'</fieldset> '."\n"."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
			$squelette_checkbox->setGroupElementTemplate( "\n".'<div class="bazar_checkbox">'."\n".'{element}'."\n".'</div>'."\n", $tableau_template[0].$tableau_template[1].$tableau_template[6]);
			$formtemplate->addGroup($checkbox, $tableau_template[0].$tableau_template[1].$tableau_template[6], $tableau_template[2].$bulledaide, "\n");
		}
	}
	elseif ($mode == 'html')
	{
		$actionbazarliste = '{{bazarliste idtypeannonce="'.$tableau_template[1].'" query="'.$query.'" ordre="'.$ordre.'"}}';
		$html = $GLOBALS['wiki']->Format($actionbazarliste);
		return $html;
	}
}

function bookmarklet(&$formtemplate, $tableau_template, $mode, $valeurs_fiche) {
	$url_bookmarklet = $GLOBALS['wiki']->href('bazarframe',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR.'&amp;action='.BAZ_ACTION_NOUVEAU.'&amp;id_typeannonce='.$GLOBALS['_BAZAR_']['id_typeannonce']);
	$htmlbookmarklet = "<div class=\"BAZ_info\">
	<a href=\"javascript:var wleft = (screen.width-700)/2; var wtop=(screen.height-530)/2 ;window.open('".$url_bookmarklet."&amp;bf_titre='+escape(document.title)+'&amp;url='+encodeURIComponent(location.href)+'&amp;description='+escape(document.getSelection()), 'Veille collective', 'height=530,width=700,left='+wleft+',top='+wtop+',toolbar=no,location=no,directories=no,status=no,scrollbars=no,resizable=no,menubar=no');void 0;\">Veille Collective</a> << deplacer ce lien dans votre barre des favoris pour y acceder facilement.</div>";
	if ($mode == 'saisie') {
		return $htmlbookmarklet;
	}
}


/* +--Fin du code ----------------------------------------------------------------------------------------+
*
* $Log: formulaire.fonct.inc.php,v $
*
*
* +-- Fin du code ----------------------------------------------------------------------------------------+
*/
?>
