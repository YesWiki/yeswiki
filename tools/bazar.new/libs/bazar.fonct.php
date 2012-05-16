<?php
/*vim: set expandtab tabstop=4 shiftwidth=4: */
// +------------------------------------------------------------------------------------------------------+
// | PHP version 5                                                                                      |
// +------------------------------------------------------------------------------------------------------+
// | Copyright (C) 2005 Outils-Reseaux (accueil@outils-reseaux.org)                                       |
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
*
* Fonctions du module bazar
*
*
* @package bazar
* @author        Florian Schmitt <florian@outils-reseaux.org>
* @author        Alexandre Granier <alexandre@tela-botanica.org>
* @copyright     Outils-Reseaux 2000-2010
* @version       $Revision: 1.10 $ $Date: 2010/03/04 14:19:03 $
*
*/

// +------------------------------------------------------------------------------------------------------+
// |                                            ENTETE du PROGRAMME                                       |
// +------------------------------------------------------------------------------------------------------+

/** baz_afficher_menu() - Prepare les boutons du menu de bazar et renvoie le html
*
* @return   string  HTML
*/
function baz_afficher_menu() {
	$res = '<div class="subnav">'."\n".'<ul class="nav nav-pills">'."\n";

	// Gestion de la vue par defaut
	if (!isset($_GET[BAZ_VARIABLE_VOIR])) {
		$_GET[BAZ_VARIABLE_VOIR] = BAZ_VOIR_DEFAUT;
	}

	//partie consultation d'annonces
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_CONSULTER))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR] == BAZ_VOIR_CONSULTER) ? ' class="active"' : '';
		$res .= '<li id="menu_consulter"'.$class.'>'."\n".'<a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER).'">'.BAZ_RECHERCHER_FICHES.'</a>'."\n".'</li>'."\n";
	}

	//partie saisie d'annonces
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_SAISIR))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_SAISIR ) ? ' class="active"' : '';
		$res .='<li id="menu_deposer"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR).'">'.BAZ_SAISIR_FICHE.'</a>'."\n".'</li>'."\n";
	} 
	
	/*// Mes fiches
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_MES_FICHES))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR] == BAZ_VOIR_MES_FICHES) ? ' class="active"' : '';
		$res .= '<li id="menu_mes_fiches"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_MES_FICHES).'">'.BAZ_VOIR_VOS_FICHES.'</a>'."\n".'</li>'."\n";
	}
	*/
	
	//partie affichage listes
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_LISTES))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_LISTES) ? ' class="active"' : '';
		$res .= '<li id="menu_listes"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES).'">'.BAZ_LISTES.'</a></li>'."\n" ;
	}
	
	//partie affichage formulaire
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_FORMULAIRE))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_FORMULAIRE) ? ' class="active"' : '';
		$res .= '<li id="menu_formulaire"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_FORMULAIRE).'">'.BAZ_FORMULAIRES.'</a></li>'."\n" ;
	}


/*
	//partie import
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_IMPORTER))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_IMPORTER) ? ' class="active"' : '';
		$res .= '<li id="menu_import"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_IMPORTER).'">'.BAZ_IMPORTER.'</a></li>'."\n" ;
	}
*/
	//partie export
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_EXPORTER))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_EXPORTER) ? ' class="active"' : '';
		$res .= '<li id="menu_export"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_EXPORTER).'">'.BAZ_EXPORTER.'</a></li>'."\n" ;
	}

	//partie abonnement aux annonces
	if (strstr(BAZ_VOIR_AFFICHER, strval(BAZ_VOIR_S_ABONNER))) {
		$class = (isset($_GET[BAZ_VARIABLE_VOIR]) && $_GET[BAZ_VARIABLE_VOIR]==BAZ_VOIR_S_ABONNER) ? ' class="active"' : '';
		$res .= '<li id="menu_inscrire"'.$class.'><a href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_S_ABONNER).'">'.BAZ_RSS.'</a></li>'."\n" ;
	}
	
	// Au final, on ferme la liste et la div du menu
	$res .= '</ul>'."\n".'<div class="clear"></div>'."\n".'</div>'."\n";
	return $res;
}


/** baz_afficher_liste_fiches_utilisateur () - Affiche la liste des fiches bazar d'un utilisateur
*
* @return   string  HTML
*/
function baz_afficher_liste_fiches_utilisateur() {

	$res = '';

	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$res .= '<h2 class="titre_mes_fiches">'.BAZ_VOS_FICHES.'</h2>'."\n";
	}

	//test si l'on est identifie pour voir les fiches
	$nomwiki = $GLOBALS['wiki']->getUser();
	if ( $nomwiki ) {
		$tableau_fiches = baz_requete_recherche_fiches('', '', $GLOBALS['_BAZAR_']['form_id'], $GLOBALS['_BAZAR_']['form_category'], 1, $nomwiki["name"]);
        if (count($tableau_fiches)>0) {
        	$res .= baz_afficher_liste_resultat($tableau_fiches, false);
        } else {
        	$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_FICHES_SAUVEES_EN_VOTRE_NOM.'</div>'."\n";
        }
	} else  {
		$res .= '<div class="alert alert-info">'.BAZ_IDENTIFIEZ_VOUS_POUR_VOIR_VOS_FICHES.'</div>'."\n";
	}

	$res .= '<ul class="BAZ_liste liste_action">'."\n".	'<li><a class="ajout_fiche" href="'.
			$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR).'" title="'.BAZ_SAISIR_UNE_NOUVELLE_FICHE.'">'.BAZ_SAISIR_UNE_NOUVELLE_FICHE.'</a></li>'."\n".'</ul>'."\n";

	return $res;
}

/**
 *
 * interface de choix des fiches a importer
 */
function baz_afficher_formulaire_import() {
	$output = '';

	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$output .= '<h2 class="titre_import">'.BAZ_IMPORT_CSV.'</h2>'."\n";
	}

	if (!isset($categorienature)) $categorienature = NULL;
	$id_type_fiche = (isset($_POST['id_type_fiche'])) ? $_POST['id_type_fiche'] : '';

	if (isset($_POST['submit_file'])) {
		$row = 1;
		$val_formulaire = baz_valeurs_formulaire($id_type_fiche);
		$GLOBALS['_BAZAR_']['form_id'] = $id_type_fiche;
		$GLOBALS['_BAZAR_']['form_category'] = $val_formulaire['form_category'];
		$tableau = formulaire_valeurs_template_champs($val_formulaire['field']);

		if (($handle = fopen($_FILES['fileimport']['tmp_name'], "r")) !== FALSE) {
		    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
				//la premiere ligne contient les infos sur les noms des champs
				if ($row == 1) {
					$tab_labels = array_map('utf8_decode', $data) ;
					$row++;
				} else {
					$data = array_map('utf8_decode', $data) ;
					
					$num = count($data);
			        $row++;
			        for ($c=0; $c < $num; $c++) {

						//la premiere ligne est l'identifiant de la fiche
						if ($c==0) {
							if ($data[$c] != '') {
								$GLOBALS['_BAZAR_']['id_fiche'] = $data[$c] ;
							}
							else {
								$GLOBALS['_BAZAR_']['id_fiche'] = 'NomWiki &agrave; calculer' ;
							}
							$output .= '<strong>'.$tab_labels[$c].'</strong>'.' : '.$GLOBALS['_BAZAR_']['id_fiche']. "<br />\n";
						}
	
						//si une valeur est presente, on l'affiche et on la rajoute au tableau des valeurs a importer pour la fiche
				        elseif ($data[$c]!='') {
							//echo $tableau[$c-1][2].' - '.$tab_labels[$c].' : '.$data[$c].'<br />';
							$key = array_search($tab_labels[$c], $tableau[$c]);
							var_dump($tableau[$c][1]);
							$output .= '<strong>'.$tab_labels[$c].'</strong>'.' : '.$data[$c] . "<br />\n";
				        }
			        }
			        $output .= '<hr />';
			        //baz_insertion_fiche($valeur);
				}
		    }
		    fclose($handle);
		}
	} else {

		//On choisit un type de fiches pour parser le csv en consequence
		$tab_form = baz_valeurs_tous_les_formulaires($categorienature);
		$output .= '<form method="post" action="'.$GLOBALS['wiki']->href('','','').'" enctype="multipart/form-data">'."\n";

		//s'il y a plus d'un choix possible, on propose
		if (count($tab_form)>=1) {
			$output .= '<div class="form_line">'."\n".'<div class="formulaire_label">'."\n".
						BAZ_TYPE_FICHE.' :</div>'."\n".'<div class="form_input">';
			$output .= '<select name="id_type_fiche" onchange="if ($(\'select[name=id_type_fiche] option:selected\').val()!=\'\') {this.form.submit();}">'."\n";
			if ($id_type_fiche!='')	$output .=	'<option value="" selected="selected">'.BAZ_CHOISIR.'</option>'."\n";
			foreach ($tab_form as $ligne) {
				$output .= '<option value="'.key($ligne).'"'.(($id_type_fiche == key($ligne)) ? ' selected="selected"' : '').'>'.$ligne[key($ligne)]['form_name'].'</option>'."\n";
			}
			$output .= '</select>'."\n".'</div>'."\n".'</div>'."\n";
		}
		//sinon c'est vide
		else {
			$output .= '<div class="alert alert-info">'.BAZ_PAS_DE_FORMULAIRES_TROUVES.'</div>'."\n";
		}

		if ($id_type_fiche != '') {
			$val_formulaire = baz_valeurs_formulaire($id_type_fiche);
			$output .= '<div class="form_line">'."\n".'<div class="formulaire_label">'."\n".
					BAZ_FICHIER_CSV_A_IMPORTER.' :</div>'."\n".'<div class="form_input">';
			$output .= '<input type="file" name="fileimport" id="idfileimport" />'."\n".'</div>'."\n"."\n".'</div>';
			$output .= '<div class="form_line">'."\n".'<div class="formulaire_label">&nbsp;</div>'."\n".'<div class="form_input"><input name="submit_file" type="submit" value="'.BAZ_IMPORTER_CE_FICHIER.'" />'."\n".'</div>'."\n".'</div>'."\n";
			$output .= '<div class="alert alert-info">'."\n".BAZ_ENCODAGE_CSV."\n".'</div>'."\n";

			//on parcourt le template du type de fiche pour fabriquer un csv pour l'exemple
			$tableau = formulaire_valeurs_template_champs($val_formulaire['field']);
			$csv = '"PageWiki",' ;
			$nb = 0;
			foreach ($tableau as $ligne) {
				if ($ligne[0] != 'labelhtml') {
					$csv .= '"'.str_replace('"','""',$ligne[2]).((isset($ligne[9]) && $ligne[9]==1) ? ' *' : '').'", ';
					$nb++;
				}
			}
			$csv = substr(trim($csv),0,-1)."\r\n";
			for ($i=1; $i<4; $i++) {
				$csv .= '"NomWiki'.$i.'",';
				for ($j=1; $j<($nb+1); $j++) {
					$csv .= '"ligne '.$i.' - champ '.$j.'", ';
				}
				$csv = substr(trim($csv),0,-1)."\r\n";
			}

			$output .= '<em>'.BAZ_EXEMPLE_FICHIER_CSV.$val_formulaire["form_name"].'</em>'."\n";
			$output .= '<pre style="height:125px; white-space:pre; padding:5px; word-wrap:break-word; border:1px solid #999; overflow:auto; ">'."\n".$csv."\n".'</pre>'."\n";
		}

		$output .= '</form>'."\n";
	}
	return $output;
}

/**
 *
 * interface de choix des fiches a exporter
 */
function baz_afficher_formulaire_export() {
	$output = '';

	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$output .= '<h2 class="titre_export">'.BAZ_EXPORT_CSV.'</h2>'."\n";
	}

	if (!isset($categorienature)) $categorienature = NULL;
	$id_type_fiche = (isset($_POST['id_type_fiche'])) ? $_POST['id_type_fiche'] : '';

	$tab_formulaire = baz_valeurs_tous_les_formulaires($categorienature);
	
	$output .= '<form method="post" action="'.$GLOBALS['wiki']->Href().
				(($GLOBALS['wiki']->GetMethod()!='show')? '/'.$GLOBALS['wiki']->GetMethod() : '&amp;'.BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_EXPORTER).'">'."\n";

	//s'il y a plus d'un choix possible, on propose
	if (count($tab_formulaire)>=1) {
		$output .= '<div class="form_line">'."\n".'<div class="formulaire_label">'."\n".
					BAZ_TYPE_FICHE.' :</div>'."\n".'<div class="form_input">';
		$output .= '<select name="id_type_fiche" onchange="javascript:this.form.submit();">'."\n";

		//si l'on n'a pas deja choisit de fiche, on demarre sur l'option CHOISIR, vide
		if (!isset($_POST['id_type_fiche'])) $output .= '<option value="" selected="selected">'.BAZ_CHOISIR.'</option>'."\n";

		//on dresse la liste de types de fiches
		foreach ($tab_formulaire as $form) {
			$idform = key($form);
			$output .= '<option value="'.$idform.'"'.(($id_type_fiche == $idform) ? ' selected="selected"' : '').'>'.$form[$idform]['form_name'].'</option>'."\n";
		}
		$output .= '</select>'."\n".'</div>'."\n".'</div>'."\n";
	}
	//sinon c'est vide
	else {
		$output .= '<div class="alert alert-info">'.BAZ_PAS_DE_FORMULAIRES_TROUVES.'</div>'."\n";
	}
	$output .= '</form>'."\n";

	if ($id_type_fiche != '') {
		$val_formulaire = baz_valeurs_formulaire($id_type_fiche);

		//on parcourt le template du type de fiche pour fabriquer un csv pour l'exemple
		$tableau = formulaire_valeurs_template_champs($val_formulaire['field']);
		$csv = '"PageWiki",' ;
		$nb = 0 ;
		$tab_champs = array();
		foreach ($tableau as $ligne) {
			if ($ligne[0] != 'labelhtml') {
				if ($ligne[0] == 'liste' || $ligne[0] == 'checkbox' || $ligne[0] == 'listefiche' || $ligne[0] == 'checkboxfiche') {
					$tab_champs[] = $ligne[0].'|'.$ligne[1].'|'.$ligne[6];
				} else {
					$tab_champs[] = $ligne[1];
				}
				$csv .= utf8_encode('"'.str_replace('"','""',$ligne[2]).((isset($ligne[9]) && $ligne[9]==1) ? ' *' : '').'",');
				$nb++;
			}
		}
		$csv = substr(trim($csv),0,-1)."\r\n";

		//on recupere toutes les fiches du type choisi et on les met au format csv
		$tableau_fiches = baz_requete_recherche_fiches('', 'alphabetique', $id_type_fiche, $val_formulaire['form_category']);
		$total = count($tableau_fiches);
		foreach ($tableau_fiches as $fiche) {
			//var_dump($fiche);
			$tab_valeurs = json_decode($fiche['body'], true);
			$tab_csv = array();
			$tab_csv['PageWiki'] = '"'.$tab_valeurs['id_fiche'].'"';

			foreach ($tab_champs as $index) {
				$tabindex = explode('|',$index);
				$index = str_replace('|','',$index);
				//ces types de champs necessitent un traitement particulier
				if ( $tabindex[0]=='liste' || $tabindex[0]=='checkbox' || $tabindex[0]=='listefiche' || $tabindex[0]=='checkboxfiche') {
					$html = $tabindex[0]($toto, array(0 => $tabindex[0],1 => $tabindex[1] ,6 => $tabindex[2]), 'html', array($index => $tab_valeurs[$index]));
					$tabhtml = explode ('</span>', $html);
					$tab_valeurs[$index] = utf8_encode(html_entity_decode(trim(strip_tags($tabhtml[1]))));
				}
				if (isset($tab_valeurs[$index])) $tab_csv[] = html_entity_decode('"'.str_replace('"','""',$tab_valeurs[$index]).'"'); else $tab_csv[] = '';
			}

			$csv .=  implode(',',$tab_csv)."\r\n";
		}

		$output .= '<em>'.BAZ_VISUALISATION_FICHIER_CSV_A_EXPORTER.$val_formulaire["form_name"].' - '.BAZ_TOTAL_FICHES.' : '.$total.'</em>'."\n";
		$output .= '<pre style="height:125px; white-space:pre; padding:5px; word-wrap:break-word; border:1px solid #999; overflow:auto; ">'."\n".utf8_decode($csv)."\n".'</pre>'."\n";

		//on genere le fichier
		$chemin_destination = BAZ_CHEMIN_UPLOAD.'bazar-export-'.$id_type_fiche.'.csv';
		//verification de la presence de ce fichier, s'il existe deja , on le supprime
		if (file_exists($chemin_destination)) {
			unlink($chemin_destination);
		}
		$fp = fopen($chemin_destination, 'w');
		fwrite($fp, $csv);
		fclose($fp);
		chmod ($chemin_destination, 0755);

		//on cree le lien vers ce fichier
		$output .= '<a href="'.$chemin_destination.'" class="link-csv-file" title="'.BAZ_TELECHARGER_FICHIER_EXPORT_CSV.'">'.BAZ_TELECHARGER_FICHIER_EXPORT_CSV.'</a>'."\n";
	}
	
	return $output;
}

/** baz_formulaire() - Renvoie le formulaire pour les saisies ou modification des fiches
*
* @param	string	action du formulaire : soit formulaire de saisie, soit inscription dans la base de donnees, soit formulaire de modification, soit modification de la base de donnees
* @param	string	url de renvois du formulaire (facultatif)
* @param	array	valeurs de la fiche en cas de modification (facultatif)
*
* @return   string  HTML
*/
function baz_formulaire($mode, $url = '', $valeurs = '') {
	$res = '';
	if ($url == '') {
		$lien_formulaire = $GLOBALS['wiki']->href('',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR);
		
		//Definir le lien du formulaire en fonction du mode de formulaire choisi
		if ($mode == BAZ_CHOISIR_TYPE_FICHE) {
			$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_NOUVEAU;
		}
		if ($mode == BAZ_ACTION_NOUVEAU) {
			if (!isset($_POST['accept_condition']) && $GLOBALS['_BAZAR_']['terms_of_use'] != NULL) {
				$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_NOUVEAU;
			} else {
				$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_NOUVEAU_V;
			}
		}
		if ($mode == BAZ_ACTION_MODIFIER) {
			if (!isset($_POST['accept_condition']) && $GLOBALS['_BAZAR_']['terms_of_use'] != NULL) {
				$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER;
			} else {
				$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER_V;
			}
			$lien_formulaire .= '&amp;id_fiche='.$GLOBALS['_BAZAR_']['id_fiche'];
		}
		if ($mode == BAZ_ACTION_MODIFIER_V) {
			$lien_formulaire .= '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER_V.'&amp;id_fiche='.$GLOBALS['_BAZAR_']['id_fiche'];
		}
	}

	//contruction du squelette du formulaire
	$formtemplate = '<form action="'.($url ? $url : $lien_formulaire).'" method="post" id="bazarform" enctype="multipart/form-data">'."\n";

	//antispam
	$formtemplate .= '<input name="antispam" type="hidden" value="0" />'."\n";

	//------------------------------------------------------------------------------------------------
	//AFFICHAGE DU FORMULAIRE GENERAL DE CHOIX DU TYPE DE FICHE
	//------------------------------------------------------------------------------------------------
	if ($mode == BAZ_CHOISIR_TYPE_FICHE) {
		if (isset($GLOBALS['_BAZAR_']['form_id']) && $GLOBALS['_BAZAR_']['form_id'] != NULL) {
			$mode = BAZ_ACTION_NOUVEAU ;
		} else {
			//titre
			if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
				$res .= '<h2 class="titre_saisir_fiche">'.BAZ_SAISIR_UNE_NOUVELLE_FICHE.'</h2>'."\n";
			}

			//requete pour obtenir le nom et la description des types d'annonce
			$tab_formulaire = baz_valeurs_tous_les_formulaires($GLOBALS['_BAZAR_']['form_category']);

			if (count($tab_formulaire)==1) {

				$tab_type_formulaire = array_shift($tab_formulaire);
				$GLOBALS['_BAZAR_']['form_id'] = key($tab_type_formulaire);
				$_REQUEST['form_id'] = $GLOBALS['_BAZAR_']['form_id'];
				$ligne = array_shift($tab_type_formulaire);
				$GLOBALS['_BAZAR_']['form_name']=$ligne['form_name'];
				$GLOBALS['_BAZAR_']['terms_of_use']=$ligne['terms_of_use'];
				$GLOBALS['_BAZAR_']['field']=$ligne['field'];
				/*$GLOBALS['_BAZAR_']['commentaire']=$ligne['bn_commentaire'];
				$GLOBALS['_BAZAR_']['appropriation']=$ligne['bn_appropriation'];
				$GLOBALS['_BAZAR_']['image_titre']=$ligne['bn_image_titre'];
				$GLOBALS['_BAZAR_']['image_logo']=$ligne['bn_image_logo'];*/
				$GLOBALS['_BAZAR_']['form_category'] = $ligne['form_category'];
				$mode = BAZ_ACTION_NOUVEAU;

				//on remplace l'attribut action du formulaire par l'action adequate
				$lien_formulaire .= BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_NOUVEAU_V;
				/*$attributes = array('action'=>str_replace ("&amp;", "&", $lien_formulaire->getURL()));
				$formtemplate->updateAttributes($attributes);*/
			}
			else {
				if (count($tab_formulaire)>0) {
					$res .= '<div class="alert alert-info">'.BAZ_CHOIX_TYPE_FICHE.'</div>'."\n";

					foreach ($tab_formulaire as $type_fiche => $formulaire) {
						$res .= '<h3 class="titre_categorie_fiche">'.$type_fiche.'</h3>'."\n";

						foreach ($formulaire as $nomwiki => $ligne) {
							$res .= '<a href="'. $lien_formulaire .'&amp;form_id='. $nomwiki .'" title="'.BAZ_SAISIR_FICHE_DE_CE_TYPE.'" class="lien_formulaire">'."\n";
							$res .= '<span class="BAZ_titre_liste">'.$ligne['form_name'].'</span>'."\n";
							if ($ligne['form_description'] != '') {
								$res .= ' : '.$ligne['form_description']."\n";
							}
							$res .= '</a>'."\n";
						}
					}
				} else {
					$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_FORMULAIRES_TROUVES.'</div>'."\n";
				}
			}
		}
	}

	//------------------------------------------------------------------------------------------------
	//AFFICHAGE DU FORMULAIRE CORRESPONDANT AU TYPE DE FICHE CHOISI PAR L'UTILISATEUR
	//------------------------------------------------------------------------------------------------

	if ($mode == BAZ_ACTION_NOUVEAU) {
		// Affichage du modele de formulaire
		$res .= baz_formulaire_des_fiches('saisie', $formtemplate, ($url ? $url : $lien_formulaire));
	}


	//------------------------------------------------------------------------------------------------
	// CAS DE LA MODIFICATION D'UNE FICHE (FORMULAIRE DE MODIFICATION)
	//------------------------------------------------------------------------------------------------
	if ($mode == BAZ_ACTION_MODIFIER) {
		$res .= baz_formulaire_des_fiches('modification', $formtemplate, ($url ? $url : $lien_formulaire), $valeurs);
	}

	//------------------------------------------------------------------------------------------------
	// CAS DE L'AJOUT D'UNE FICHE
	//------------------------------------------------------------------------------------------------
	if ($mode == BAZ_ACTION_NOUVEAU_V && $_POST['antispam']==1) {
		baz_insertion_fiche($_POST) ;	
		
		// Redirection vers la fiche
		$GLOBALS['wiki']->Redirect($GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER.'&'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&id_fiche='.$GLOBALS['_BAZAR_']['id_fiche'].'&message=ajout_ok'));		
		exit;
	}

	//------------------------------------------------------------------------------------------------
	// CAS DE LA MODIFICATION D'UNE FICHE (VALIDATION ET MAJ)
	//------------------------------------------------------------------------------------------------
	if ($mode == BAZ_ACTION_MODIFIER_V && $_POST['antispam']==1) {
		if (baz_a_le_droit( 'saisie_fiche', $GLOBALS['wiki']->GetPageOwner($GLOBALS['_BAZAR_']['id_fiche']))) {
			baz_mise_a_jour_fiche($_POST) ;	
		
			// Redirection vers la fiche
			$GLOBALS['wiki']->Redirect($GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER.'&'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&id_fiche='.$GLOBALS['_BAZAR_']['id_fiche'].'&message=modif_ok'));	
			exit;
		}
	}

	return $res;
}

/** baz_formulaire_des_fiches() - Genere le formulaire de saisie d'une annonce
*
* @param	string type de formulaire: insertion ou modification
* @param	mixed objet quickform du formulaire
* @param	string	url de renvois du formulaire (facultatif)
* @param	array	valeurs de la fiche en cas de modification (facultatif)
*
* @return   string  code HTML avec formulaire
*/
function baz_formulaire_des_fiches($action, $formtemplate, $url, $valeurs = '') {
	$res = '';
	
	//titre de la rubrique
	$res .= '<h2 class="titre_type_fiche">'.BAZ_TITRE_SAISIE_FICHE.'&nbsp;'.$GLOBALS['_BAZAR_']['form_name'].'</h2>'."\n";

	//si le type de formulaire requiert une acceptation des conditions on affiche les conditions
	if ($GLOBALS['_BAZAR_']['terms_of_use']!='' && !isset($_POST['accept_condition'])) {
		
		$tab_formulaire_fiche['form_line'][] = $GLOBALS['wiki']->Format($GLOBALS['_BAZAR_']['terms_of_use']);
		$tab_formulaire_fiche['form_line'][] = '<div class="form_line">
				<label for="accept_condition"><span class="required_symbol">*&nbsp;</span>'.BAZ_ACCEPTE_CONDITIONS.'</label>
				<input type="checkbox" required="required" id="accept_condition" name="accept_condition" />
			</div>'."\n";
	
				
		$tab_formulaire_fiche['hidden_inputs'] = '<input type="hidden" name="form_id" value="'.$_REQUEST['form_id'].'" />'."\n";
		$tab_formulaire_fiche['hidden_inputs'] .= '<input type="hidden" name="antispam" value="0" />'."\n";
		
		//si on a passe une url, on est dans le cas d'une page de type fiche_bazar, il nous faut le nom
		if ($url != '' && isset($GLOBALS['_BAZAR_']['id_fiche'])) {
			$tab_formulaire_fiche['hidden_inputs'] .= '<input type="hidden" name="id_fiche" value="'.$GLOBALS['_BAZAR_']['id_fiche'].'" />'."\n";
		}

	
		// Bouton d annulation : on retourne a la visualisation de la fiche saisie en cas de modification
		if ($action == BAZ_MODIFIER_FICHE) {
			$tab_formulaire_fiche['link_cancel'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&amp;id_fiche='.$GLOBALS['_BAZAR_']['id_fiche']);
			
		// Bouton d annulation : on retourne a la page wiki sans aucun choix par defaut sinon
		} else {
			$tab_formulaire_fiche['link_cancel'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), '');
		}
		
		//url : BAZ_VARIABLE_ACTION, $_GET[BAZ_VARIABLE_ACTION]);('id_fiche', $GLOBALS['_BAZAR_']['id_fiche']) ;
		$tab_formulaire_fiche['link_save'] = $url;
	}
	
	//affichage du formulaire si conditions acceptees
	else {
		//Parcours du fichier de templates, pour mettre les valeurs des champs
		//$tab_formulaire_fiche['tableau'] = formulaire_valeurs_template_champs($GLOBALS['_BAZAR_']['field']);
		
		if (!is_array($valeurs) && isset($GLOBALS['_BAZAR_']['id_fiche']) && $GLOBALS['_BAZAR_']['id_fiche']!='')
		{
			//Ajout des valeurs par defaut pour une modification
			$tab_formulaire_fiche['entry_values'] = baz_entry_values($GLOBALS['_BAZAR_']['id_fiche']);
		} 
		elseif (isset($valeurs['id_fiche'])) {
			$GLOBALS['_BAZAR_']['id_fiche'] = $valeurs['id_fiche'];
			$tab_formulaire_fiche['entry_values'] = $valeurs;
		}
		else {
		$tab_formulaire_fiche['entry_values'] = array();
		}
		
		//genere toutes les lignes du formulaire en fonction du type de champs
		$champs_formulaire = '';
		$valeursformulaire['mode'] = 'form';
		$field_values_serialised = '';
		$i=0;

		$valeursformulaire['field'] = $GLOBALS['_BAZAR_']['field'];
		foreach($valeursformulaire['field'] as $tabfield) {
			
			foreach($tabfield as $key => $value) {
				$field_values_serialised .= $key.'='.rawurlencode(utf8_encode($value)).'&';
			}
			$GLOBALS['_BAZAR_']['field'][$i]['field_values_serialised'] = htmlspecialchars(substr($field_values_serialised, 0, -1));
			if (!isset($valeursformulaire['field'][$i]['required'])) $valeursformulaire['field'][$i]['required'] = '';
			$i++;
			$field_values_serialised = '';
		}
		include_once('tools/bazar/libs/squelettephp.class.php');
		$formulaire = new SquelettePhp('tools/bazar/presentation/templates/generate_form.tpl.html');
		$formulaire->set($valeursformulaire);
		$champs_formulaire = $formulaire->analyser();
		
		$tab_formulaire_fiche['champs_formulaire'] = $champs_formulaire;
		/*for ($i=0; $i<count($GLOBALS['_BAZAR_']['field']); $i++) {
			$tab_formulaire_fiche['form_line'][] = $tab_formulaire_fiche['tableau'][$i][0]($formtemplate, $tab_formulaire_fiche['tableau'][$i], 'saisie', $tab_formulaire_fiche['entry_values']) ;
		}
		$tab_formulaire_fiche['field'] = $GLOBALS['_BAZAR_']['field'];*/
		$tab_formulaire_fiche['hidden_inputs'] = '<input type="hidden" name="form_id" value="'.$_REQUEST['form_id'].'" />'."\n";
		$tab_formulaire_fiche['hidden_inputs'] .= '<input type="hidden" name="antispam" value="0" />'."\n";
		
		//si on a passe une url, on est dans le cas d'une page de type fiche_bazar, il nous faut le nom
		if ($url != '' && isset($GLOBALS['_BAZAR_']['id_fiche'])) {
			$tab_formulaire_fiche['hidden_inputs'] .= '<input type="hidden" name="id_fiche" value="'.$GLOBALS['_BAZAR_']['id_fiche'].'" />'."\n";
		}

		// Bouton d annulation : on retourne a la visualisation de la fiche saisie en cas de modification
		if ($action == BAZ_MODIFIER_FICHE) {
			$tab_formulaire_fiche['link_cancel'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&amp;id_fiche='.$GLOBALS['_BAZAR_']['id_fiche']);
			
		// Bouton d annulation : on retourne a la page wiki sans aucun choix par defaut sinon
		} else {
			$tab_formulaire_fiche['link_cancel'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), '');
		}
		
		$tab_formulaire_fiche['link_save'] = $url;
	}

	//Affichage a l'ecran
	include_once('tools/bazar/libs/squelettephp.class.php');
	$formformulaire_fiche = new SquelettePhp('tools/bazar/presentation/templates/formulaire_des_fiches.tpl.html');
	$formformulaire_fiche->set($tab_formulaire_fiche);
	return $formformulaire_fiche->analyser();
}


/** baz_requete_bazar_fiche() - prepare la requete d'insertion ou de MAJ de la fiche en supprimant de la valeur POST les valeurs inadequates
 * puis en l'encodant en JSON
 *
 * @global   mixed L'objet contenant les valeurs issues de la saisie du formulaire
 * @return   string Tableau des valeurs a sauver dans la PageWiki, au format JSON
 *
 **/
function baz_requete_bazar_fiche($valeur) {
	//on enleve les champs hidden pas necessaires a la fiche
	unset($valeur["valider"]);
	unset($valeur["MAX_FILE_SIZE"]);
	unset($valeur["antispam"]);

	//pour les checkbox, on met les resultats sur une ligne
	foreach ($valeur as $cle => $val) {
		if (is_array($val)) {
			$valeur[$cle] = implode(',', array_keys($val));
		}
	}
	$valeur['statut_fiche'] = BAZ_ETAT_VALIDATION;

	$tableau = formulaire_valeurs_template_champs($GLOBALS['_BAZAR_']['field']);
	for ($i=0; $i<count($tableau); $i++) {
		$tab = $tableau[$i][0]($formtemplate, $tableau[$i], 'requete', $valeur);
		if (is_array($tab)) $valeur = array_merge($valeur, $tab);
	}

	$valeur['date_maj_fiche'] = date( 'Y-m-d H:i:s', time() );

	//pour une insertion d'une nouvelle fiche, on genere l'id de la fiche
	if (!isset($GLOBALS['_BAZAR_']['id_fiche'])) {
		// l'identifiant (sous forme de NomWiki) est genere a partir du titre
		$GLOBALS['_BAZAR_']['id_fiche'] = genere_nom_wiki($valeur['bf_titre']);
	}
	$valeur['id_fiche'] = $GLOBALS['_BAZAR_']['id_fiche'];

	//on encode en utf-8 pour reussir a encoder en json
	$valeur = array_map("utf8_encode", $valeur);

	return json_encode($valeur);
}

/** baz_insertion_fiche() - inserer une nouvelle fiche
*
* @array   Le tableau des valeurs a inserer
*
* @return   void
*/
function baz_insertion_fiche($valeur) {
	//on teste au moins l'existence du titre car sans titre ca peut bugguer serieusement
	if (isset($valeur['bf_titre']) && $valeur['bf_titre'] != '') {
		// ===========  Insertion d'une nouvelle fiche ===================
		//createur de la fiche
		if ($GLOBALS['_BAZAR_']['nomwiki']!='' && $GLOBALS['_BAZAR_']['nomwiki']!=NULL) {
			$valeur['createur'] = $GLOBALS['_BAZAR_']['nomwiki']['name'];
		} else {
			$valeur['createur'] = BAZ_ANONYME;
		}
		$valeur['categorie_fiche'] = $GLOBALS['_BAZAR_']['form_category'];
		$valeur['date_creation_fiche'] = date( 'Y-m-d H:i:s', time() );
		if (!isset($_REQUEST['date_debut_validite_fiche'])) {
			$valeur['date_debut_validite_fiche'] = date( 'Y-m-d', time() );
			$valeur['date_fin_validite_fiche'] = "0000-00-00";
		}
		$valeur = baz_requete_bazar_fiche($valeur);

		//on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
		$GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);

		//on cree un triple pour specifier que la page wiki creee est une fiche bazar
		$GLOBALS["wiki"]->InsertTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/type', 'fiche_bazar', '', '');

		// Envoie d un mail aux administrateurs
		if (BAZ_ENVOI_MAIL_ADMIN) {
			/*include_once('Mail.php');
			include_once('Mail/mime.php');
			$lien = str_replace("/wakka.php?wiki=","",$GLOBALS['wiki']->config["base_url"]);
			$sujet = remove_accents('['.str_replace("http://","",$lien).'] nouvelle fiche ajoutee : '.$_POST['bf_titre']);
			$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
			$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
			$GLOBALS['_BAZAR_']['url']->addQueryString('id_fiche', $GLOBALS['_BAZAR_']['id_fiche']) ;
			$text = 'Voir la fiche sur le site pour l\'administrer : '.$GLOBALS['_BAZAR_']['url']->getUrl();
			$texthtml = '<br /><br /><a href="'.$GLOBALS['_BAZAR_']['url']->getUrl().'" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
			$fichier = 'tools/bazar/presentation/bazar.css';
			$style = file_get_contents($fichier);
			$style = str_replace('url(', 'url('.$lien.'/tools/bazar/presentation/', $style);
			$fiche = str_replace('src="tools', 'src="'.$lien.'/tools', baz_voir_fiche(0, $GLOBALS['_BAZAR_']['id_fiche'])).$texthtml;
			$html = '<html><head><style type="text/css">'.$style.'</style></head><body>'.$fiche.'</body></html>';

			$crlf = "\n";
			$hdrs = array(
			              'From'    => BAZ_ADRESSE_MAIL_ADMIN,
			              'Subject' => $sujet
			              );

			$mime = new Mail_mime($crlf);

			$mime->setTXTBody($text);
			$mime->setHTMLBody($html);

			//do not ever try to call these lines in reverse order
			$body = $mime->get();
			$hdrs = $mime->headers($hdrs);

			//$mail =& Mail::factory('mail');

			//on va chercher les admins
			$requeteadmins = 'SELECT value FROM '.$GLOBALS['wiki']->config["table_prefix"].'triples WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
			$resultatadmins = $GLOBALS['wiki']->LoadAll($requeteadmins);
			$ligne = $resultatadmins->fetchRow(DB_FETCHMODE_ASSOC);
			$tabadmin = explode("\n", $ligne['value']);
			foreach ($tabadmin  as $line) {
				$admin = $GLOBALS['wiki']->LoadUser(trim($line));
				$mail->send($admin['email'], $hdrs, $body);
			}*/
		}
		return ;
	}
	// sinon on met un message d'erreur
	else echo '<div class="alert alert-error">'.BAZ_FICHE_NON_SAUVEE_PAS_DE_TITRE.'</div>';
}




/** baz_mise_a_jour() - Mettre a jour une fiche
*
* @global   Le contenu du formulaire de saisie de l'annonce
* @return   void
*/
function baz_mise_a_jour_fiche($valeur) {
	$valeur = array_merge(baz_entry_values($GLOBALS['_BAZAR_']['id_fiche']), $valeur);
	$valeur = baz_requete_bazar_fiche($valeur, $GLOBALS['_BAZAR_']['form_id']);
	//on sauve les valeurs d'une fiche dans une PageWiki, pour garder l'historique
	$GLOBALS["wiki"]->SavePage($GLOBALS['_BAZAR_']['id_fiche'], $valeur);

	// Envoie d un mail aux administrateurs
		if (BAZ_ENVOI_MAIL_ADMIN) {
			/*include_once('Mail.php');
			include_once('Mail/mime.php');
			$lien = str_replace("/wakka.php?wiki=","",$GLOBALS['wiki']->config["base_url"]);
			$sujet = remove_accents('['.str_replace("http://","",$lien).'] fiche modifiee : '.$_POST['bf_titre']);
			$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
			$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
			$GLOBALS['_BAZAR_']['url']->addQueryString('id_fiche', $GLOBALS['_BAZAR_']['id_fiche']) ;
			$text = 'Voir la fiche sur le site pour l\'administrer : '.$GLOBALS['_BAZAR_']['url']->getUrl();
			$texthtml = '<br /><br /><a href="'.$GLOBALS['_BAZAR_']['url']->getUrl().'" title="Voir la fiche">Voir la fiche sur le site pour l\'administrer</a>';
			$fichier = 'tools/bazar/presentation/bazar.css';
			$style = file_get_contents($fichier);
			$style = str_replace('url(', 'url('.$lien.'/tools/bazar/presentation/', $style);
			$fiche = str_replace('src="tools', 'src="'.$lien.'/tools', baz_voir_fiche(0, $GLOBALS['_BAZAR_']['id_fiche'])).$texthtml;
			$html = '<html><head><style type="text/css">'.$style.'</style></head><body>'.$fiche.'</body></html>';

			$crlf = "\n";
			$hdrs = array(
			              'From'    => BAZ_ADRESSE_MAIL_ADMIN,
			              'Subject' => $sujet
			              );

			$mime = new Mail_mime($crlf);

			$mime->setTXTBody($text);
			$mime->setHTMLBody($html);

			//do not ever try to call these lines in reverse order
			$body = $mime->get();
			$hdrs = $mime->headers($hdrs);

			$mail =& Mail::factory('mail');

			//on va chercher les admins
			$requeteadmins = 'SELECT value FROM '.$GLOBALS['wiki']->config["table_prefix"].'triples WHERE resource="ThisWikiGroup:admins" AND property="http://www.wikini.net/_vocabulary/acls" LIMIT 1';
			$resultatadmins = $GLOBALS['_BAZAR_']['db']->query($requeteadmins);
			$ligne = $resultatadmins->fetchRow(DB_FETCHMODE_ASSOC);
			$tabadmin = explode("\n", $ligne['value']);
			foreach ($tabadmin  as $line) {
				$admin = $GLOBALS['wiki']->LoadUser(trim($line));
				$mail->send($admin['email'], $hdrs, $body);
			}*/
		}
	return;
}


/** baz_suppression() - Supprime une fiche
*
* @global   L'identifiant de la fiche a supprimer
* @return   void
*/
function baz_suppression($idfiche) {
	if ($idfiche != '') {
		$valeur = baz_entry_values($idfiche);
		if ( baz_a_le_droit( 'saisie_fiche', $valeur['bf_ce_utilisateur'] ) ) {

			/*//suppression des valeurs des champs texte, checkbox et liste
			$requete = 'DELETE FROM '.BAZ_PREFIXE.'fiche_valeur_texte WHERE bfvt_ce_fiche = "'.$idfiche.'"';
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
			if (DB::isError($resultat)) {
				return ('Echec de la requete<br />'.$resultat->getMessage().'<br />'.$resultat->getDebugInfo().'<br />'."\n") ;
			}

			//suppression des valeurs des champs texte long
			$requete = 'DELETE FROM '.$GLOBALS['wiki']->config["table_prefix"].'triples WHERE resource = "'.$idfiche.'"';
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
			if (DB::isError($resultat)) {
				return ('Echec de la requete<br />'.$resultat->getMessage().'<br />'.$resultat->getDebugInfo().'<br />'."\n") ;
			}

			//TODO:suppression des fichiers et images associÃƒÂ©es

			//suppression de la fiche dans '.BAZ_PREFIXE.'fiche
			$requete = 'DELETE FROM '.BAZ_PREFIXE.'fiche WHERE bf_id_fiche = "'.$idfiche.'"';
			$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
			if (DB::isError($resultat)) {
				echo ('Echec de la requete<br />'.$resultat->getMessage().'<br />'.$resultat->getDebugInfo().'<br />'."\n") ;
			}*/

			//on supprime les pages wiki crees
			$GLOBALS['wiki']->DeleteOrphanedPage($idfiche);
			$GLOBALS["wiki"]->DeleteTriple($GLOBALS['_BAZAR_']['id_fiche'], 'http://outils-reseaux.org/_vocabulary/type', NULL, '', '');


			//on nettoie l'url, on retourne a la consultation des fiches
			$GLOBALS['_BAZAR_']['url']->addQueryString ('message', 'delete_ok') ;
			$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
			$GLOBALS['_BAZAR_']['url']->removeQueryString (BAZ_VARIABLE_VOIR) ;
			$GLOBALS['_BAZAR_']['url']->removeQueryString ('id_fiche') ;
			header ('Location: '.$GLOBALS['_BAZAR_']['url']->getURL()) ;
			exit;
		}
		else {
			echo '<div class="BAZ_error">'.BAZ_PAS_DROIT_SUPPRIMER.'</div>'."\n";
		}
	}
	return ;
}


/** publier_fiche () - Publie ou non dans les fichiers XML la fiche bazar d'un utilisateur
*
* @global boolean Valide: oui ou non
* @return   void
*/
function publier_fiche($valid) {
	//l'utilisateur a t'il le droit de valider
	if ( baz_a_le_droit( 'valider_fiche' ) ) {
		if ($valid==0) {
			$requete = 'UPDATE '.BAZ_PREFIXE.'fiche SET  bf_statut_fiche=2 WHERE bf_id_fiche="'.$_GET['id_fiche'].'"' ;
			echo '<div class="alert alert-info">'.BAZ_FICHE_PAS_VALIDEE.'</div>'."\n";
		}
		else {
			$requete = 'UPDATE '.BAZ_PREFIXE.'fiche SET  bf_statut_fiche=1 WHERE bf_id_fiche="'.$_GET['id_fiche'].'"' ;
			echo '<div class="alert alert-info">'.BAZ_FICHE_VALIDEE.'</div>'."\n";
		}

		// ====================Mise a jour de la table '.BAZ_PREFIXE.'fiche====================
		$resultat = $GLOBALS['_BAZAR_']['db']->query($requete) ;
		if (DB::isError($resultat)) {
			echo ($resultat->getMessage().$resultat->getDebugInfo()) ;
		}
		unset ($resultat) ;
		//TODO envoie mail annonceur
	}
	return;
}


/** baz_liste_rss() affiche le formulaire qui permet de s'inscrire pour recevoir des annonces d'un type
*
*   @return  string    le code HTML
*/
function baz_liste_rss() {
	$res = '';
	//titre
	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$res .= '<h2 class="titre_s_abonner_fiche">'.BAZ_S_ABONNER_AUX_FICHES.'</h2>'."\n";
	}

	$tab_valeurs_formulaire = baz_valeurs_tous_les_formulaires($GLOBALS['_BAZAR_']['form_category']);

	$lien_RSS = $GLOBALS['wiki']->href('rss', $GLOBALS['wiki']->GetPageTag());

	$liste='';

	foreach ($tab_valeurs_formulaire as $type_fiche => $formulaire) {
		$liste .= '<h3 class="titre_categorie_fiche">'.$type_fiche.'</h3>'."\n";
		$liste .= '<ul class="BAZ_liste">'."\n";
		foreach ($formulaire as $nomwiki => $ligne) {
			$lienflux = $lien_RSS.'&amp;form_id='.$nomwiki;
			$liste .= '<li>'."\n".'<a class="lien_rss" href="'.$lienflux.'"><img src="'.BAZ_CHEMIN.'presentation/images/BAZ_rss.png" alt="'.BAZ_RSS.'" />&nbsp;';
			$liste .= $ligne['form_name'].'</a>'."\n";
			$liste .= '</li>'."\n";
		}
		$ligne .= '</ul>'."\n";
	}

	if ($liste!='') {
		$res .= '<ul class="BAZ_liste">'."\n".'<li><a class="lien_rss" href="'.$lien_RSS.'"><img src="'.BAZ_CHEMIN.'presentation/images/BAZ_rss.png" alt="'.BAZ_RSS.'" />&nbsp;<strong>'.BAZ_FLUX_RSS_GENERAL.'</strong></a></li></ul>'."\n".$liste."\n";
	} else {
		$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_FORMULAIRES_TROUVES.'</div>'."\n";
	}

	return $res;
}


/** baz_formulaire_des_formulaires() retourne le formulaire de saisie des formulaires
*
*   @return  string    le code HTML
*/
function baz_formulaire_des_formulaires($mode, $valeursformulaire = '') {
	
	// on prepare la prévisualisation du formulaire
	$previsualisation_formulaire = '';
	if (isset($valeursformulaire['field']) && is_array($valeursformulaire['field'])) {	
		$valeursformulaire['mode'] = 'edit_form';
		$field_values_serialised = '';
		$i=0;
		//$valeursformulaire['field'] = array_map_recursive('utf8_decode',$valeursformulaire['field']);
		foreach($valeursformulaire['field'] as $tabfield) {
			
			foreach($tabfield as $key => $value) {
				$field_values_serialised .= $key.'='.rawurlencode(utf8_encode($value)).'&';
			}
			$valeursformulaire['field'][$i]['field_values_serialised'] = htmlspecialchars(substr($field_values_serialised, 0, -1));
			if (!isset($valeursformulaire['field'][$i]['required'])) $valeursformulaire['field'][$i]['required'] = '';
			$i++;
			$field_values_serialised = '';
		}
		include_once('tools/bazar/libs/squelettephp.class.php');
		$formulaire = new SquelettePhp('tools/bazar/presentation/templates/generate_form.tpl.html');
		$formulaire->set($valeursformulaire);
		$previsualisation_formulaire = $formulaire->analyser();
	}
	$tab_formulaire['interface_ajout_champs'] = $previsualisation_formulaire;
	
	// identifiant du formulaire TODO: est ce vraiment nécéssaire vu qu'il est passé en GET
	$tab_formulaire['idformulaire'] = (isset($_GET['idformulaire']) ? $_GET['idformulaire'] : '');
	
	// valeurs par défaut du formulaire, pour le mode édition
	$tab_formulaire['default'] = $valeursformulaire;

	// on va chercher tous les groupes d'utilisateurs wiki et on les formatte en liste
	$tab_groups = $GLOBALS['wiki']->GetGroupsList();
	$tab_formulaire['options_group'] = '';
	foreach ($tab_groups as $group) {
		$tab_formulaire['options_group'] .= '<option value="'.$group.'">Groupe '.$group.'</option>'."\n";
	}
	
	// création du lien de sauvegarde pour le formulaire
	$tab_formulaire['url_formulaire'] = $GLOBALS['wiki']->href('', 
		$GLOBALS['wiki']->GetPageTag(),
		BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_FORMULAIRE.'&action_formulaire='.$mode.(isset($_GET['idformulaire']) ? '&idformulaire='.$_GET['idformulaire'] : ''));
			
	// création du lien pour l'annulation
	$tab_formulaire['cancel_link'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_FORMULAIRE);
	
	$js_lists_as_variables = "\n".'var lists_and_entries = new Array(2);'; 
	$tab_formulaire['lists'] = '';
	// gestion des sources pour les listes : a partir des listes entrées
	$lists = baz_valeurs_toutes_les_listes();
	if (is_array($lists) && count($lists)> 0) {
		$js_lists_as_variables .= "\n".'lists_and_entries["lists"] = new Array('.count($lists).');'."\n";
		$tab_formulaire['lists'] .= '<optgroup id="list_source" label="Liste">';
		foreach($lists as $idlist => $list) {
			$tab_formulaire['lists'] .= '<option value="'.$idlist.'">'.$list['titre_liste'].'</option>'."\n";
			$js_lists_as_variables .= 'lists_and_entries[\'lists\'][\''.$idlist.'\'] = {'."\n";
			foreach ($list['label'] as $key =>$val) {
				$js_lists_as_variables .= ' "'.$key.'": "'.$val.'",';
			}
			$js_lists_as_variables = substr($js_lists_as_variables, 0, -1).'};';
		}
		$tab_formulaire['lists'] .= '</optgroup>';
	} else {
		$tab_formulaire['warning_list'] = 'Vous n\'avez pas encore cr&eacute;&eacute; de listes, si vous en avez besoin pour votre formulaire, cliquez d\'abord sur <strong>l\'onglet Listes</strong> pour les cr&eacute;er dans un premier temps.';
	}
	
	// gestion des sources pour les listes : a partir des formulaires entrés
	$forms = baz_valeurs_tous_les_formulaires();
	if (is_array($forms) && count($forms)> 0) {
		$js_lists_as_variables .= 'lists_and_entries["entries"] = new Array('.count($forms).');'."\n";
		$tab_formulaire['lists'] .= '<optgroup id="type_sheet_source" label="Type de fiche">';                                               
		foreach($forms as $form_category) {
			foreach($form_category as $idform => $form) {
				$tab_formulaire['lists'] .= '<option value="'.$idform.'">'.$form['form_name'].'</option>'."\n";
			}
		}
		$tab_formulaire['lists'] .= '</optgroup>';
	}

		// on ajoute les javascripts nécéssaires à l'édition du formulaire
	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').
'<script type="text/javascript" src="tools/bazar/libs/jquery-ui-1.8.17.custom.min.js"></script>
<script type="text/javascript" src="tools/bazar/libs/bazar.edit_forms.js"></script>
<script type="text/javascript">'.$js_lists_as_variables.'</script>'."\n";


	// on génère l'affiche du formulaire des formulaires à partir du template
	include_once('tools/bazar/libs/squelettephp.class.php');
	$formformulaire = new SquelettePhp('tools/bazar/presentation/templates/formulaire_des_formulaires.tpl.html');
	$formformulaire->set($tab_formulaire);
	
	// on renvoie l'HTML du formulaire des formulaires
	return $formformulaire->analyser();
}

/** baz_formulaire_des_listes() retourne le formulaire de saisie des listes
*
*   @return  Object    le code HTML
*/
function baz_formulaire_des_listes($mode, $valeursliste = '') {

	//champs du formulaire
	if (isset($_GET['idliste'])) {
		$tab_formulaire['NomWiki'] = $_GET['idliste'];
	}

	$html_valeurs_listes =  '<div class="control-group">'."\n".
							'<label class="control-label">'.BAZ_VALEURS_LISTE.'</label>'."\n".
							'<div class="controls">'."\n".
							'<ul class="valeur_liste">'."\n";
	if (is_array($valeursliste)) {
		$tab_formulaire['titre_liste'] = $valeursliste['titre_liste'];
		$elements = $valeursliste['label'];
		$i = 0;
		foreach($elements as $id => $label) {
			$i++;
			$html_valeurs_listes .=
								'<li class="liste_ligne" id="row'.$i.'">'.
								'<a href="#" title="'.BAZ_DEPLACER_L_ELEMENT.'" class="handle_liste"></a>'.
								'<input required type="text" placeholder="Id" name="id['.$i.']" value="'.htmlspecialchars($id).'" class="span1" />'.
								'<input required type="text" placeholder="Texte" name="label['.$i.']" value="'.htmlspecialchars($label).'" class="span3" />'.
								'<input type="hidden" name="ancienid['.$i.']" value="'.htmlspecialchars($id).'" />'.
								'<input type="hidden" name="ancienlabel['.$i.']" value="'.htmlspecialchars($label).'" />'.
								'<a href="#" class="BAZ_lien_supprimer suppression_label_liste"></a>'.
								'</li>'."\n";
		}
	} else {
		$html_valeurs_listes .= '<li class="liste_ligne" id="row1">'.
								'<a href="#" title="'.BAZ_DEPLACER_L_ELEMENT.'" class="handle_liste"></a>'.
								'<input required type="text" placeholder="Id" name="id[1]" class="span1" />'.
								'<input required type="text" placeholder="Texte" name="label[1]" class="span3" />'.
								'<a href="#" class="BAZ_lien_supprimer suppression_label_liste"></a>'.
								'</li>'."\n";
	}

	$html_valeurs_listes .= '</ul>'."\n".
							'<a href="#" class="btn btn-primary ajout_label_liste" title="'.BAZ_AJOUTER_LABEL_LISTE.'"><i class="icon-plus icon-white"></i>&nbsp;'.BAZ_AJOUTER_LABEL_LISTE.'</a>'."\n".
							'</div>'."\n".'</div>'."\n".
							'<div class="clear"></div>'."\n";
							
	//on rajoute une variable globale pour mettre le javascript en plus a la fin
	$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').'
<script type="text/javascript" src="tools/bazar/libs/jquery-ui-1.8.17.custom.min.js"></script>
<script type="text/javascript" src="tools/bazar/libs/bazar.edit_lists.js"></script>'."\n";
	$tab_formulaire['valeurs_listes'] = $html_valeurs_listes;
	
	$tab_formulaire['url_formulaire'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(),
			BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES.'&action='.$mode.(isset($_GET['idliste']) ? '&idliste='.$_GET['idliste'] : ''));
	$tab_formulaire['url_annuler'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES);

	include_once('tools/bazar/libs/squelettephp.class.php');
	$formlistes = new SquelettePhp('tools/bazar/presentation/templates/formulaire_des_listes.tpl.html');
	$formlistes->set($tab_formulaire);
	return $formlistes->analyser();
}


/** baz_gestion_formulaire() affiche le listing des formulaires et permet de les modifier
*
*   @return  string    le code HTML
*/
function baz_gestion_formulaire() {
	$res = '';
	
	//titre
	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$res .= '<h2 class="titre_gestion_formulaire">'.BAZ_GESTION_FORMULAIRE.'</h2>'."\n";
	}

	// il y a un formulaire a modifier
	if (isset($_GET['action_formulaire']) && $_GET['action_formulaire']=='modif') {

		//on verifie que la page en question est bien une PageWiki de type formulaire
		if ($GLOBALS['wiki']->GetTripleValue($_GET['idformulaire'], 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'formulaire') {
			$valeurs_formulaire = baz_valeurs_formulaire($_GET['idformulaire']);
			$res .= baz_formulaire_des_formulaires('modif_v', $valeurs_formulaire);
		}
		else {
			return '<div class="alert alert-error">'.BAZ_PAGEWIKI_PAS_FORMULAIRE.'</div>';
		}

	//il y a un nouveau formulaire a saisir
	} elseif (isset($_GET['action_formulaire']) && $_GET['action_formulaire']=='new') {
		$res .=  baz_formulaire_des_formulaires('new_v');
 
	// il y a des donnees pour ajouter un nouveau formulaire, ou qu'on modifie un formulaire qu'on a le droit de modifier
	} elseif (isset($_GET['action_formulaire']) && ($_GET['action_formulaire']=='new_v' || ($_GET['action_formulaire']=='modif_v' && $GLOBALS['wiki']->HasAccess('write', $_POST['idformulaire'])))) {
		// on efface les champs non nécessaires à sauvegarder
		unset($_POST["valider"]);
		unset($_POST["choose_field_type"]);

		// on decoupe le champs caché field en morceaux
		$i = 0;
		foreach ($_POST['field'] as $field) {
			$field = utf8_decode(rawurldecode($field));
			parse_str($field, $champs[$i]);	
			$i++;
		}
		$_POST['field'] = $champs;

		if ($_GET['action_formulaire']=='new_v') {
			// on genere un NomWiki a partir du titre du formulaire
			$_POST['idformulaire'] = genere_nom_wiki(BAZ_FORMULAIRE.' '.$_POST["form_name"]);

			// on cree un triple pour specifier que la PageWiki creee est un formulaire
			$GLOBALS["wiki"]->InsertTriple($_POST['idformulaire'], 'http://outils-reseaux.org/_vocabulary/type', 'formulaire', '', '');

			// on informe de la création du formulaire
			$res .= '<div class="alert alert-success"><a data-dismiss="alert" class="close">&times;</a>'.BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE.'</div>'."\n";
		} else {
			// on informe de la modification du formulaire
			$res .= '<div class="alert alert-success"><a data-dismiss="alert" class="close">&times;</a>'.BAZ_FORMULAIRE_MODIFIE.'</div>'."\n";
		}

		// on encode en utf-8 pour reussir a encoder en json
		$valeurs_formulaire = array_map_recursive("utf8_encode", $_POST);

		// on sauve les valeurs du formulaire dans une PageWiki, pour garder l'historique
		$GLOBALS["wiki"]->SavePage($_POST['idformulaire'], json_encode($valeurs_formulaire));

	// il y a un id de formulaire a supprimer
	} elseif (isset($_GET['action_formulaire']) && $_GET['action_formulaire']=='delete' && $GLOBALS['wiki']->HasAccess('write', $_GET['idformulaire'])) {

		//suppression de la PageWiki du formulaire et de son triple associe
		$GLOBALS["wiki"]->DeleteOrphanedPage($_GET['idformulaire']);
		$GLOBALS["wiki"]->DeleteTriple($_GET['idformulaire'], 'http://outils-reseaux.org/_vocabulary/type', NULL, '', '');

		//suppression des fiches associees au formulaire
		$tab_fiches_a_supprimer = baz_requete_recherche_fiches('', '', $_GET['idformulaire'], '', 1, '', '');
		foreach ($tab_fiches_a_supprimer as $fiche) {
			$entry_values = json_decode($fiche[0], true);
			baz_suppression($entry_values['id_fiche']);
		}

		$res .= '<div class="alert alert-success"><a data-dismiss="alert" class="close">&times;</a>'.BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES.'</div>'."\n";
	}

	// affichage de la liste des templates a modifier ou supprimer (on l'affiche dans tous les cas, sauf cas de modif de formulaire)
	if (!isset($_GET['action_formulaire']) || ($_GET['action_formulaire']!='modif' && $_GET['action_formulaire']!='new') ) {

		$tab_formulaires = baz_valeurs_tous_les_formulaires($GLOBALS['_BAZAR_']['form_category']);

		if (count($tab_formulaires)>0) {
			
			$liste=''; $type_formulaire='';

			foreach ($tab_formulaires as $type_fiche => $formulaire) {
				$res .= '<h3 class="titre_categorie_fiche">'.$type_fiche.'</h3>'."\n";
				$res .= '<ul class="BAZ_liste">'."\n";

				foreach ($formulaire as $nomwiki => $ligne) {
					$lien_formulaire = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_FORMULAIRE.'&amp;idformulaire='.$nomwiki);
					$res .= '<li>'."\n";
					if ($GLOBALS['wiki']->HasAccess('write', $nomwiki)) {
						$res .= '<a class="BAZ_lien_supprimer" href="'.$lien_formulaire.'&amp;action_formulaire=delete" onclick="javascript:return confirm(\''.BAZ_CONFIRM_SUPPRIMER_FORMULAIRE.' ?\');"></a>'."\n";
						$res .= '<a class="BAZ_lien_modifier" href="'.$lien_formulaire.'&amp;action_formulaire=modif">'.$ligne['form_name'].'</a>'."\n";
					} else {
						$res .= $ligne['form_name']."\n";
					}

					$res .='</li>'."\n";
				}

				$res .= '</ul>'."\n";
			}
		} else {
			$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_FORMULAIRES_TROUVES.'</div>'."\n";
		}

		//ajout du lien pour creer un nouveau formulaire
		$res .= '<a class="btn btn-primary" href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_FORMULAIRE.'&action_formulaire=new').'"><i class="icon-plus icon-white"></i>&nbsp;'.BAZ_NOUVEAU_FORMULAIRE.'</a>'."\n";

	}
	return $res;
}


/** baz_gestion_listes() affiche le listing des listes et permet de les modifier
*
*   @return  string    le code HTML
*/
function baz_gestion_listes() {
	
	$res = '';
	
	//titre
	if ($GLOBALS['_BAZAR_']['affiche_menu'] == "0") {
		$res .= '<h2 class="titre_gestion_liste">'.BAZ_GESTION_LISTES.'</h2>'."\n";
	}
	
	// affichage de la liste des templates a modifier ou supprimer (dans le cas ou il n'y a pas d'action selectionnee)
	if (!isset($_GET['action'])) {
		//requete pour obtenir l'id et le label des types d'annonces
		$requete = 'SELECT resource FROM '.$GLOBALS['wiki']->config['table_prefix'].'triples WHERE property="http://outils-reseaux.org/_vocabulary/type" AND value="liste" ORDER BY resource';
		$resultat = $GLOBALS['wiki']->LoadAll($requete) ;

		$liste = array();
		foreach ($resultat as $ligne) {
			$valeursliste = baz_valeurs_liste($ligne['resource']);

			$liste[$valeursliste['titre_liste']] = '<li>';
			
			if ($GLOBALS['wiki']->HasAccess('write', $ligne['resource']))  {
				$liste[$valeursliste['titre_liste']] .= '<a class="BAZ_lien_supprimer" href="'.$GLOBALS['wiki']->href('',$GLOBALS['wiki']->GetPageTag(), 
						BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_SUPPRIMER_LISTE.'&amp;idliste='.$ligne['resource']).'"  onclick="javascript:return confirm(\''.BAZ_CONFIRM_SUPPRIMER_LISTE.' ?\');"></a>'."\n";
			}

			$elements_liste = '';
			foreach ($valeursliste['label'] as $val) {
				$elements_liste .= '<option>'.$val.'</option>';
			}
			if ($elements_liste != '') {
				$affichage_liste = '&nbsp;- '.BAZ_VALEURS_LISTE.' :&nbsp;<select id="liste_'.$ligne['resource'].'">'."\n".
				'<option>'.BAZ_CHOISIR.'</option>'."\n".
				$elements_liste."\n".
				'</select>'."\n";
			} else {
				$affichage_liste = '';
			}
			if ($GLOBALS['wiki']->HasAccess('write', $ligne['resource']))  {
				$liste[$valeursliste['titre_liste']] .= '<a class="BAZ_lien_modifier" href="'.$GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(),
						BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER_LISTE.'&amp;idliste='.$ligne['resource']).'">'.
						$valeursliste['titre_liste'].'</a>'.$affichage_liste."\n";
			} else {
				$liste[$valeursliste['titre_liste']] .= $valeursliste['titre_liste'].$affichage_liste."\n";
			}

			$liste[$valeursliste['titre_liste']] .= '</li>'."\n";
		}

		if (count($liste)>0) {
			ksort($liste);
			$res .= '<ul class="BAZ_liste">'."\n";
			foreach($liste as $listederoulante) {
				$res .= $listederoulante;
			}
			$res .= '</ul>'."\n";
		} else {
			$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_LISTES.'</div>'."\n";
		}	

		//ajout du lien pour creer une nouvelle liste
		$lien_formulaire = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_NOUVELLE_LISTE);
		$res .= '<a href="'.$lien_formulaire.'" class="btn btn-primary"><i class="icon-plus icon-white"></i>&nbsp;'.BAZ_NOUVELLE_LISTE.'</a>'."\n";
	}		

	// il y a une liste a modifier
	elseif ($_GET['action']==BAZ_ACTION_MODIFIER_LISTE) {
		//recuperation des informations de la liste
		$valeursliste = baz_valeurs_liste($_GET['idliste']);
		$res .= baz_formulaire_des_listes(BAZ_ACTION_MODIFIER_LISTE_V, $valeursliste);
	}
	
	//il y a une nouvelle liste a saisir
	elseif ($_GET['action']==BAZ_ACTION_NOUVELLE_LISTE) {
		$res .= baz_formulaire_des_listes(BAZ_ACTION_NOUVELLE_LISTE_V);
	}
	
	//il y a des donnees pour ajouter une nouvelle liste
	elseif ($_GET['action']==BAZ_ACTION_NOUVELLE_LISTE_V) {
		unset($_POST["valider"]);
		$nomwikiliste = genere_nom_wiki('Liste '.$_POST['titre_liste']);
		
		//on supprime les valeurs vides et on encode en utf-8 pour reussir a encoder en json
		$i = 1;
		$valeur["label"] = array();
		foreach ($_POST["label"] as $label) {
			if (($label!=NULL || $label!='') && ($_POST["id"][$i]!=NULL || $_POST["id"][$i]!='')) {
				$valeur["label"][$_POST["id"][$i]] = $label;
				$i++;
			}
		}
		$valeur["label"] = array_map("utf8_encode", $valeur["label"]);
		$valeur["titre_liste"] = utf8_encode($_POST["titre_liste"]);

		//on sauve les valeurs d'une liste dans une PageWiki, pour garder l'historique
		$GLOBALS["wiki"]->SavePage($nomwikiliste, json_encode($valeur));
		//on cree un triple pour specifier que la PageWiki creee est une liste
		$GLOBALS["wiki"]->InsertTriple($nomwikiliste, 'http://outils-reseaux.org/_vocabulary/type', 'liste', '', '');
		
		//on redirige vers la page contenant toutes les listes, et on confirme par message la bonne saisie de la liste
		$GLOBALS["wiki"]->SetMessage(BAZ_NOUVELLE_LISTE_ENREGISTREE);
		$GLOBALS["wiki"]->Redirect($GLOBALS["wiki"]->href('',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES, false));
	}
	
	//il y a des donnees pour modifier une liste
	elseif ($_GET['action']==BAZ_ACTION_MODIFIER_LISTE_V && $GLOBALS['wiki']->HasAccess('write', $_POST['NomWiki']) ) {
		unset($_POST["valider"]);
		//on supprime les valeurs vides et on encode en utf-8 pour reussir a encoder en json
		$i = 1;
		$valeur["label"] = array();
		foreach ($_POST["label"] as $label) {
			if (($label!=NULL || $label!='') && ($_POST["id"][$i]!=NULL || $_POST["id"][$i]!='')) {
				$valeur["label"][$_POST["id"][$i]] = $label;
				$i++;
			}
		}
		$valeur["label"] = array_map("utf8_encode", $valeur["label"]);
		$valeur["titre_liste"] = utf8_encode($_POST["titre_liste"]);

		/* ----------------- TODO: gerer les suppressions de valeurs dans les fiches associees pour garantir l'integrite des donnees
		//on verifie si les valeurs des listes ont changees afin de garder de l'integrite de la base des fiches
		foreach ($_POST["ancienlabel"] as $key => $value) {
			//si la valeur de la liste a ete changee, on repercute les changements pour les fiches contenant cette valeur
			if ( isset($_POST["label"][$key]) && $value != $_POST["label"][$key] ) {
				//TODO: fonction baz_modifier_metas_liste($_POST['NomWiki'], $value, $_POST['label'][$key]);
			}
		}

		//on supprime les valeurs des listes supprimees des fiches possedants ces valeurs
		foreach ($_POST["a_effacer_ancienlabel"] as $key => $value) {
			//TODO: fonction baz_effacer_metas_liste($_POST['NomWiki'], $value);
		}
		--------------------- */

		//on sauve les valeurs d'une liste dans une PageWiki, pour garder l'historique
		$GLOBALS["wiki"]->SavePage($_POST['NomWiki'], json_encode($valeur));

		//on redirige vers la page contenant toutes les listes, et on confirme par message la bonne modification de la liste
		$GLOBALS["wiki"]->SetMessage(BAZ_LISTE_MODIFIEE);
		$GLOBALS["wiki"]->Redirect($GLOBALS["wiki"]->href('',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES, false));
	}
	
	// il y a un id de liste a supprimer
	elseif ($_GET['action']==BAZ_ACTION_SUPPRIMER_LISTE && $GLOBALS['wiki']->HasAccess('write', $_GET['idliste'])) {
		$GLOBALS["wiki"]->DeleteOrphanedPage($_GET['idliste']);
		$sql = 'DELETE FROM ' . $GLOBALS['wiki']->config["table_prefix"] . 'triples '
			. 'WHERE resource = "' . addslashes($_GET['idliste']) . '" ';
		$GLOBALS["wiki"]->Query($sql);

		//on redirige vers la page contenant toutes les listes, et on confirme par message la bonne suppression de la liste
		$GLOBALS["wiki"]->SetMessage(BAZ_LISTES_SUPPRIMEES);
		$GLOBALS["wiki"]->Redirect($GLOBALS["wiki"]->href('',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_LISTES, false));
	}

	return $res;
}


/** baz_entry_values() - Renvoie un tableau avec les valeurs par defaut du formulaire d'inscription
*
* @param    string Identifiant de la fiche
*
* @return   array   Valeurs enregistrees pour cette fiche
*/
function baz_entry_values($idfiche = '') {
	if ($idfiche != '') {
		//on verifie que la page en question est bien une page wiki
		if ($GLOBALS['wiki']->GetTripleValue($idfiche, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'fiche_bazar') {

			$valjson = $GLOBALS['wiki']->LoadPage($idfiche);
			$entry_values = json_decode($valjson["body"], true);
			$entry_values = array_map('utf8_decode', $entry_values);

			//cas ou on ne trouve pas les valeurs id_fiche et form_id
			if (!isset($entry_values['id_fiche'])) $entry_values['id_fiche'] = $idfiche;
			if (!isset($entry_values['form_id'])) $entry_values['form_id'] = $entry_values['form_id'];

			return $entry_values;
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}


/** baz_valeurs_liste() - Renvoie un tableau avec les valeurs d'une liste
*
* @param    string NomWiki de la liste
*
* @return   array   Valeurs enregistrees pour cette liste
*/
function baz_valeurs_liste($idliste) {
	if ($idliste != '') {
		//on verifie que la page en question est bien une page wiki
		if ($GLOBALS['wiki']->GetTripleValue($idliste, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'liste') {

			$valjson = $GLOBALS['wiki']->LoadPage($idliste);
			$entry_values = json_decode($valjson["body"], true);
			$entry_values['titre_liste'] = utf8_decode($entry_values['titre_liste']);
			$entry_values['label'] = array_map('utf8_decode', $entry_values['label']);

			return $entry_values;
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}


/** baz_valeurs_formulaire() - Toutes les informations du formulaire demande
*
* @param    string Identifiant de la PageWiki du formulaire
*
* @return   array
*/
function baz_valeurs_formulaire($idformulaire) {

	if ($idformulaire != '') {

		//on verifie que la page en question est bien un formulaire
		if ($GLOBALS['wiki']->GetTripleValue($idformulaire, 'http://outils-reseaux.org/_vocabulary/type', '', '') == 'formulaire') {

			$valjson = $GLOBALS['wiki']->LoadPage($idformulaire);
			$entry_values = json_decode($valjson["body"], true);
			$entry_values = array_map_recursive('utf8_decode', $entry_values);

			return $entry_values;
		}
		else {
			return false;
		}
	}
	else {
		return false;
	}
}

/** baz_valeurs_toutes_les_listes() - Toutes les informations de toutes les listes
*
*
* @param	string type de format de reponse : html (par defaut) ou json
*
* @return   array
*/
function baz_valeurs_toutes_les_listes($format = 'html')  {

	//requete pour obtenir toutes les PageWiki de type liste
	$requete_sql =  'SELECT resource FROM `'.BAZ_PREFIXE.'triples` WHERE `property`="http://outils-reseaux.org/_vocabulary/type" AND `value`="liste"';
	$nomwikilistes = $GLOBALS['wiki']->LoadAll($requete_sql);
	$valeurs_liste = '';
	if (count($nomwikilistes)>0 && is_array($nomwikilistes[0])) {
		foreach ($nomwikilistes as $nomwiki) {
			if ($format == 'html') {
				$tab_liste = baz_valeurs_liste($nomwiki['resource']);
				$valeurs_liste[$nomwiki['resource']] = $tab_liste;
			}
			elseif ($format == 'json') {
				$valjson = $GLOBALS['wiki']->LoadPage($nomwiki['resource']);
				$valeurs_liste[$nomwiki['resource']] = json_decode($valjson["body"], true);
			}
		}

		// on trie par ordre alphabetique et on renvoie le resultat
		ksort($valeurs_liste);
		return $valeurs_liste;
	}
	else {
		return false;
	}
}

/** baz_valeurs_tous_les_formulaires() - Toutes les informations de tous les formulaires d'une categorie ou de toutes les categories
*
* @param    string nom de la categorie
* @param	string type de format de reponse : html (par defaut) ou json
*
* @return   array
*/
function baz_valeurs_tous_les_formulaires($categorie = NULL, $format = 'html') {

	//requete pour obtenir toutes les PageWiki de type formulaire
	$requete_sql =  'SELECT resource FROM `'.BAZ_PREFIXE.'triples` WHERE `property`="http://outils-reseaux.org/_vocabulary/type" AND `value`="formulaire"';
	$nomwikiformulaire = $GLOBALS['wiki']->LoadAll($requete_sql);
	$valeurs_formulaire = ''; $valeurs_formulaire_rangees = array();
	if (is_array($nomwikiformulaire) && isset($nomwikiformulaire[0])) {
		foreach ($nomwikiformulaire as $nomwiki) {
			if ($format == 'html') {
				$tab_formulaire = baz_valeurs_formulaire($nomwiki['resource']);
			}
			elseif ($format == 'json') {
				$valjson = $GLOBALS['wiki']->LoadPage($nomwiki['resource']);
				$tab_formulaire = json_decode($valjson["body"], true);
			}
			//on filtre tous les formulaires de la meme categorie
			if (($categorie != NULL && $tab_formulaire['form_category'] == $categorie) || $categorie==NULL) {
				$valeurs_formulaire[$tab_formulaire['form_category']][$nomwiki['resource']] = $tab_formulaire;
			}
		}
		// on trie d'abord par categorie de formulaire
		ksort($valeurs_formulaire);
		foreach ($valeurs_formulaire as $type => $formulaires_de_la_categorie) {
			//on trie ensuite par nom du formulaire
			ksort($formulaires_de_la_categorie);
			$valeurs_formulaire_rangees[$type] = $formulaires_de_la_categorie;
		}
	}

	return $valeurs_formulaire_rangees;

}

/**  baz_voir_fiche() - Permet de visualiser en detail une fiche  au format XHTML
*
* @global boolean Rajoute des informations internes a l'application (date de modification, lien vers la page de depart de l'appli) si a 1
* @global integer Identifiant de la fiche a afficher ou mixed un tableau avec toutes les valeurs stockees pour la fiche
*
* @return   string  HTML
*/
function baz_voir_fiche($danslappli, $idfiche) {

	//si c'est un tableau avec les valeurs de la fiche
	if (is_array($idfiche)) {
		//on deplace le tableau et on donne la bonne valeur a id fiche
		$entry_values = $idfiche;
		$idfiche = $entry_values['id_fiche'];
		$tab_nature = baz_valeurs_formulaire($entry_values["form_id"]);
	}
	else {
		//on recupere les valeurs de la fiche
		$entry_values = baz_entry_values($idfiche);
		//on recupere les infos du type de fiche
		$tab_nature = baz_valeurs_formulaire($entry_values["form_id"]);
	}
	$res='';
	
	$url = $GLOBALS['wiki']->href('',$GLOBALS['wiki']->GetPageTag(),'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&amp;id_fiche='.$idfiche);
	//debut de la fiche
	$res .= '<div class="BAZ_cadre_fiche BAZ_cadre_fiche_'.$tab_nature['bn_label_class'].'">'."\n";

	//affiche le type de fiche pour la vue consulter
	if ($danslappli==1) {$res .= '<h2 class="BAZ_titre BAZ_titre_'.$tab_nature['bn_label_class'].'">'.$tab_nature['form_name'].'</h2>'."\n";}

	//Partie la plus importante : apres avoir recupere toutes les valeurs de la fiche, on genere l'affichage html de cette derniere
	$tableau = formulaire_valeurs_template_champs($tab_nature['field']);
	for ($i=0; $i<count($tableau); $i++) {
		$res .= $tableau[$i][0]($formtemplate, $tableau[$i], 'html', $entry_values);
	}

	//informations complementaires (id fiche, etat publication,... )
	if ($danslappli==1) {
		$res .= '<div class="BAZ_fiche_info BAZ_fiche_info_'.$tab_nature['bn_label_class'].'">'."\n";
		//affichage du nom de la PageWiki de la fiche et de son proprietaire
		$res .= $GLOBALS['wiki']->Format($idfiche.', '.(($GLOBALS['wiki']->GetPageOwner($idfiche)!='') ? BAZ_ECRITE.' '.$GLOBALS['wiki']->GetPageOwner($idfiche) : ''));
		// TODO:ajouter stats $res .= BAZ_NB_VUS.$entry_values['bf_nb_consultations'].BAZ_FOIS.'</span>'."\n";

		//affichage de l'etat de validation
		if ($entry_values['statut_fiche']==1) {
			if ($entry_values['date_debut_validite_fiche'] != '0000-00-00' && $entry_values['date_fin_validite_fiche'] != '0000-00-00') {
			$res .= '<span class="BAZ_rubrique BAZ_rubrique_'.$tab_nature['bn_label_class'].'">'.BAZ_PUBLIEE.':</span> '.BAZ_DU.
					' '.strftime('%d.%m.%Y &agrave; %H:%M', strtotime($entry_values['date_debut_validite_fiche'])).' '.
					BAZ_AU.' '.strftime('%d.%m.%Y &agrave; %H:%M', strtotime($entry_values['date_fin_validite_fiche'])).'<br />'."\n";
			}
		}
		else {
			$res .= '<span class="BAZ_rubrique BAZ_rubrique_'.$tab_nature['bn_label_class'].'">'.BAZ_PUBLIEE.':</span> '.BAZ_NON.'<br />'."\n";
		}

		//affichage des infos et du lien pour la mise a jour de la fiche
		$res .= ', <span class="date_creation">'.BAZ_DATE_CREATION.' '.strftime('%d.%m.%Y &agrave; %H:%M',strtotime($entry_values['date_creation_fiche'])).'</span>';
		$res .= ', <span class="date_mise_a_jour">'.BAZ_DATE_MAJ.' '.strftime('%d.%m.%Y &agrave; %H:%M',strtotime($entry_values['date_maj_fiche'])).'</span>.';

		//seul le createur ou un admin peut faire des actions sur la fiche
		if ( $GLOBALS['wiki']->UserIsAdmin() || $GLOBALS['wiki']->UserIsOwner($idfiche) ) {
			$res .= '<div class="BAZ_actions_fiche BAZ_actions_fiche_'.$tab_nature['bn_label_class'].'">'."\n";
			$res .= '<ul>'."\n";

			$url = $GLOBALS['wiki']->href('',$GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR.'&amp;id_fiche='.$idfiche);
			
			//ajouter action de validation (pour les admins)
			if ( baz_a_le_droit( 'valider_fiche' ) ) {											
				if ($entry_values['statut_fiche']==0||$entry_values['statut_fiche']==2) {
					$lien_publie = '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_PUBLIER;
					$label_publie = BAZ_VALIDER_LA_FICHE;
					$class_publie = '_valider';
				} elseif ($entry_values['statut_fiche']==1) {
					$lien_publie = '&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_PAS_PUBLIER;
					$label_publie = BAZ_INVALIDER_LA_FICHE;
					$class_publie = '_invalider';
				}
				$res .= '<li><a class="BAZ_lien'.$class_publie.'" href="'.$url.$lien_publie.'">'.$label_publie.'</a></li>'."\n";
			}
			
			//lien modifier la fiche			
			$lien_modifier = $url.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER;
			$res .= '<li><a class="BAZ_lien_modifier" href="'.$lien_modifier.'">'.BAZ_MODIFIER_LA_FICHE.'</a></li>'."\n";

			//lien supprimer la fiche
			$lien_supprimer = $url.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_SUPPRESSION;
			$res .= '<li><a class="BAZ_lien_supprimer" href="'.$lien_supprimer.'" onclick="javascript:return confirm(\''.
				BAZ_CONFIRM_SUPPRIMER_FICHE.'\');">'.BAZ_SUPPRIMER_LA_FICHE.'</a></li>'."\n";
			$res .= '</ul>'."\n";
			$res .= '</div><!-- fin div BAZ_actions_fiche -->'."\n";
		}
		$res .= '</div><!-- fin div BAZ_fiche_info -->'."\n";

	}

	//fin de la fiche
	$res .= '</div><!-- fin div BAZ_cadre_fiche  -->'."\n";

	return $res ;
}


/** baz_a_le_droit() Renvoie true si la personne a le droit d'acceder a la fiche
*
*   @param  string  type de demande (action bazar)
*   @param  string  identifiant, soit d'un formulaire, soit d'une fiche, soit d'un type de fiche
*
*   return  boolean	vrai si l'utilisateur a le droit, faux sinon
*/
function baz_a_le_droit( $demande, $id = '' ) {
    //cas d'une personne identifiee
    $nomwiki = $GLOBALS['wiki']->getUser();
    if (is_array($nomwiki)) {
		//l'administrateur peut tout faire
		if ($GLOBALS['wiki']->UserIsInGroup('admins')) {
			return true;
		}
		else {
			//seuls les admins peuvent gerer les formulaires
			if ($demande == BAZ_OBTENIR_TOUTES_LES_LISTES_ET_TYPES_DE_FICHES) {
				return false;
			}
			//pour la saisie d'une fiche, si la personne identifiee est l'auteur ou que la fiche n'a pas d'auteur, on peut l'editer
			if ($demande == BAZ_ACTION_SUPPRESSION || $demande == BAZ_ACTION_MODIFIER) {
				if ($id == $nomwiki['name'] || $id == '' ) return true;
				else return false;
			}
			//pour la validation d'une fiche, pour l 'instant seul les admins peuvent valider une fiche
			elseif ($demande == 'valider_fiche') {
				return false;
			}
			//pour la saisie d'un formulaire ou d'une liste, pour l 'instant seul les admins ont le droit
			elseif ($demande == 'saisie_formulaire' || $demande == 'saisie_liste') {
				return false;
			}
			//pour la liste des fiches saisies, il suffit d'etre identifie
			elseif ($demande == 'voir_mes_fiches') {
				return true;
			}
			//les autres demandes sont reservees aux admins donc non!
			else {
				return false;
			}
		}
	}
	//cas d'une personne non identifiee
	else {
		return false;
	}



}

/** array_map_recursive() Traite l'ensemble des valeurs d'un tableau avec une fonction choisie, de manière recursive
*
*   @param  string  fonction à utiliser
*   @param  array  	tableau à traiter
*
*   return  array le tableau traité
*/
function array_map_recursive($callback, $arr) {
    $ret = array();
    foreach($arr as $key => $val) {
        if(is_array($val)) $ret[$key] = array_map_recursive($callback, $val);
        else $ret[$key] = $callback($val);
    }
    return $ret;
}

/** remove_accents() Renvoie une chaine de caracteres avec les accents en moins
*
*   @param  string  chaine de caracteres avec de potentiels accents a enlever
*
*   return  string	chaine de caracteres, sans accents
*/
function remove_accents( $string ) {
    $string = htmlentities($string);
    return preg_replace("/&([a-z])[a-z]+;/i","$1",$string);
}


/** genere_nom_wiki() Prends une chaine de caracteres, et la tranforme en NomWiki unique, en la limitant a 50 caracteres et en mettant 2 majuscules
*	Si le NomWiki existe dejaÂ , on propose recursivement NomWiki2, NomWiki3, etc..
*
*   @param  string  chaine de caracteres avec de potentiels accents a enlever
*   @param	integer	nombre d'iteration pour la fonction recursive (1 par defaut)
*
*
*   return  string	chaine de caracteres, en NomWiki unique
*/
function genere_nom_wiki($nom, $occurence = 1) {
	// si la fonction est appelee pour la premiere fois, on nettoie le nom passe en parametre
	if ($occurence == 1 || $occurence == 0) {
		// les noms wiki ne doivent pas depasser les 50 caracteres, on coupe a 48, histoire de pouvoir ajouter un chiffre derriere si nom wiki dÃƒÂ©ja existant
		// plus traitement des accents
		// plus on met des majuscules au debut de chaque mot et on fait sauter les espaces
		$temp = explode(" ", ucwords(strtolower(remove_accents(substr($nom, 0, 47)))));
		$nom = '';
		foreach($temp as $mot) {
			// on vire d'eventuels autres caracteres speciaux
			$nom .= preg_replace('/[^a-zA-Z0-9]/', '', trim($mot));
		}

		// on verifie qu'il y a au moins 2 majuscules, sinon on en rajoute une a la fin
		$var = preg_replace('/[^A-Z]/', '', $nom);
		if (strlen($var)<2)	{
			$last = ucfirst(substr($nom, strlen($nom) - 1));
			$nom = substr($nom, 0, -1).$last;
		}
	}
	// si on en est a plus de 2 occurences, on supprime le chiffre precedent et on ajoute la nouvelle occurence
	elseif ($occurence>2) {
		$nb = -1*strlen(strval($occurence-1));
		$nom = substr($nom, 0, $nb).$occurence;
	}
	// cas ou l'occurence est la deuxieme : on reprend le NomWiki en y ajoutant le chiffre 2
	else {
		$nom = $nom.$occurence;
	}

	// on verifie que la page n'existe pas deja ou qu'on ne soit pas limite a une seule occurence: si c'est le cas on le retourne
	if (!is_array($GLOBALS['wiki']->LoadPage($nom) || $occurence == 0)) {
		return $nom;
	}
	// sinon, on rappele recursivement la fonction jusqu'a ce que le nom aille bien
	else {
		$occurence++;
		return genere_nom_wiki($nom, $occurence);
	}

}


/** baz_rechercher() Formate la liste de toutes les fiches
*
*   @return  string    le code HTML a afficher
*/
function baz_rechercher() {

	//creation du lien pour le formulaire de recherche
	$tab_formulaire_recherche['search_link'] = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_DEFAUT.'&'.BAZ_VARIABLE_ACTION.'='.BAZ_MOTEUR_RECHERCHE, 0);
	

	//on recupere la liste des formulaires, a afficher dans une liste deroulante pour la recherche
	$tab_formulaires = baz_valeurs_tous_les_formulaires();

	foreach ($tab_formulaires as $type_fiche => $formulaire) {
		foreach ($formulaire as $nomwiki => $ligne) {
			$tableau_typeformulaires[] = $nomwiki;
			$type_formulaire_select[$nomwiki] = $ligne['form_name'].' ('.$type_fiche.')';
		}
	}

	$type_fiche_choisie = isset($_REQUEST['form_id']);
	$formtemplate = '';
	$formtemplate_champs_recherche_supplementaires = '';
	if (count($tab_formulaires)>1) {
		$formtemplate .= '<div class="control-group">'."\n".
			'<label for="form_id" class="control-label">'.BAZ_TYPE_FICHE.'</label>'."\n";
		$formtemplate .= '<div class="controls">'."\n".
			'	<select name="form_id" onchange="javascript:this.form.submit();">'."\n".
			'		<option value="toutes">'.BAZ_TOUS_TYPES_FICHES.'</option>'."\n";
		foreach ($type_formulaire_select as $nomwikiformulaire => $formulaire_select) {
			$formtemplate .= '		<option value="'.$nomwikiformulaire.'"';
			if ($type_fiche_choisie && $_REQUEST['form_id']==$nomwikiformulaire) { 
				$formtemplate .= ' selected="selected"';
			}
			$formtemplate .= '>'.$formulaire_select.'</option>'."\n";
		}
		$formtemplate .= '	</select>'."\n".'</div>'."\n".'</div>'."\n";
		
		// on ajoute les champs de recherche du type de fiche choisi
		if ($type_fiche_choisie) {
			$tab_nature = baz_valeurs_formulaire($_REQUEST['form_id']);
			/* TODO : Réparer l'ajout de champs formulaires
			 * 
			 * $tableau = formulaire_valeurs_template_champs($GLOBALS['_BAZAR_']['field']) ;
			for ($i=0; $i<count($tableau); $i++) {
				if (($tableau[$i][0] == 'liste' || $tableau[$i][0] == 'checkbox' ||$tableau[$i][0] == 'listefiche' || $tableau[$i][0] == 'checkboxfiche' || $tableau[$i][0] == 'labelhtml')) {
					$formtemplate_champs_recherche_supplementaires .= $tableau[$i][0]($formtemplate, $tableau[$i], 'formulaire_recherche', '') ;
				}
			}*/
		}
	}
	else {
		$formtemplate .= '<input type="hidden" name="form_id" value="'.$GLOBALS['_BAZAR_']['form_id'].'" />'."\n";
	}

	//requete pour obtenir l'id, le nom et prenom de toutes les personnes ayant depose une fiche
	// dans le but de construire l'element de formulaire select avec les noms des emetteurs de fiche
	if (BAZ_RECHERCHE_PAR_EMETTEUR) {
		//TODO: gestion des emetteurs
	} else {
		$formtemplate .= '<input type="hidden" value="tous" name="personnes">'."\n";
	}
	

	//affichage du formulaire
	$tab_formulaire_recherche['search_query'] = (isset($_POST['recherche_mots_cles']) ? $_POST['recherche_mots_cles'] : '' );
	$tab_formulaire_recherche['formtemplate'] = $formtemplate;
	$tab_formulaire_recherche['champs_recherche_supplementaires'] = $formtemplate_champs_recherche_supplementaires;
	include_once('tools/bazar/libs/squelettephp.class.php');
	$formlistes = new SquelettePhp('tools/bazar/presentation/templates/formulaire_des_recherches.tpl.html');
	$formlistes->set($tab_formulaire_recherche);
	$res = $formlistes->analyser();

	//si la recherche n'a pas encore ete effectuee, on affiche les 10 dernieres fiches
    if (!isset($_REQUEST['form_id'])) {
        $tableau_dernieres_fiches = baz_requete_recherche_fiches('', '', $GLOBALS['_BAZAR_']['form_id'], $GLOBALS['_BAZAR_']['form_category'], 1, '', 10);
        if (count($tableau_dernieres_fiches) > 0 ) {
     		$res .= '<h2>'.BAZ_DERNIERES_FICHES.'</h2>';
        	$res .= baz_afficher_liste_resultat($tableau_dernieres_fiches, false);
        } else {
        	$res .= '<div class="alert alert-info">'.BAZ_PAS_DE_FICHES.'</div>'."\n";
        }
	}
	//la recherche a ete effectuee, on etablie la requete SQL
	else {
		$tableau_fiches = baz_requete_recherche_fiches('', '', $_REQUEST['form_id'], $GLOBALS['_BAZAR_']['form_category'], 1, '');
		$res .= baz_afficher_liste_resultat($tableau_fiches);
	}

	return $res;
}

/**
 * Cette fonction récupère tous les parametres passés pour la recherche, et retourne un tableau de valeurs des fiches
 */
function baz_requete_recherche_fiches($tableau_criteres = '', $tri = '', $form_id = '', $categorie_fiche = '', $statut = 1, $personne='', $nb_limite='') 
{
	$nb_jointures=0;
	
	//requete pour récupérer toutes les PageWiki étant des fiches bazar
    $requete_pages_wiki_bazar_fiches = 'SELECT DISTINCT resource FROM '.BAZ_PREFIXE.'triples WHERE value = "fiche_bazar" AND property = "http://outils-reseaux.org/_vocabulary/type" ORDER BY resource ASC';
    
    //requete d'obtention des valeurs d'une fiche
    $requete = 'SELECT DISTINCT body FROM '.BAZ_PREFIXE.'pages WHERE latest="Y" AND comment_on = \'\'';
    
   	//on limite à la catégorie choisie
    if ($categorie_fiche != '' && $categorie_fiche != NULL ) {
    	$requete .= ' AND body LIKE \'%"categorie_fiche":"'.utf8_encode($categorie_fiche).'"%\'';
    }
    
    //on limite à la langue choisie
	if (isset($GLOBALS['_BAZAR_']['langue'])) {
		//$requete .= ' AND body LIKE \'%"langue":"'.utf8_encode($GLOBALS['_BAZAR_']['langue']).'"%\'' ;
	}
	
	//on limite au type de fiche
	if ($form_id != '' && $form_id != NULL) {
		$requete .= ' AND body LIKE \'%"form_id":"'.utf8_encode($form_id).'"%\'' ;
	}
	
	//statut de validation
	$requete .= ' AND body LIKE \'%"statut_fiche":"'.$statut.'"%\'' ;
    
	//si une personne a été précisée, on limite la recherche sur elle
	if ($personne != '') {
		$requete .= ' AND body LIKE \'%"createur":"'.utf8_encode($personne).'"%\'' ;
	}
	
	
    $requete .= ' AND tag IN ('.$requete_pages_wiki_bazar_fiches.')';

	$requeteSQL = '';

	//preparation de la requete pour trouver les mots cles
	if ( isset($_REQUEST['recherche_mots_cles']) && $_REQUEST['recherche_mots_cles'] != BAZ_MOT_CLE ) {
		//decoupage des mots cles
		$recherche = explode(' ', $_REQUEST['recherche_mots_cles']) ;
		$nbmots = count($recherche);
		if ($nbmots>0) {
			$requeteSQL .= ' AND (';
			for ($i=0; $i<$nbmots; $i++) {
				if ($i>0) $requeteSQL.=' OR ';
				$requeteSQL.=' body LIKE "%'.$recherche[$i].'%"';
			}
			$requeteSQL .= ')';
		}
	}
	
    //on parcourt le tableau post pour agrémenter la requete les valeurs passées dans les champs liste et checkbox du moteur de recherche
	if ($tableau_criteres == '') {
		$tableau_criteres = array();
		//si l'on est passé par le mot de recherche, on transforme les spécifications de recherche sur les liste et checkbox 
		if (isset($_POST['rechercher'])) {
			reset($_POST);
			while (list($nom, $val) = each($_POST)) {		
				if ($nom != 'recherche_mots_cles' && $nom != 'rechercher' && $nom != 'personnes' && $nom != 'recherche_effectuee' &&
				    $nom != 'form_id' && $val != 0) {			
					if (is_array($val)) {
						$val = implode(',', array_keys($val));
					}
					$tableau_criteres[$nom] = $val;
				}
			}
		} 
	}
	
	reset($tableau_criteres);	
	while (list($nom, $val) = each($tableau_criteres)) {
		$valcrit = explode(',',$val);
		if (is_array($valcrit) && count($valcrit)>1) {
			$requeteSQL .= ' AND (';
			$first  = true;
			foreach ($valcrit as $critere) {
				if (!$first) $requeteSQL .= ' OR ';
				$requeteSQL .= '(body REGEXP BINARY \'"'.$nom.'":"[^"]*'.$critere.'[^"]*"\')';
				$first = false;
			}	
			$requeteSQL .= ')';	
		} 
		else {
			$requeteSQL .= ' AND (body REGEXP BINARY \'"'.$nom.'":"[^"]*'.$val.'[^"]*"\')';
		} 
	}

	if ($requeteSQL != '') {
		$requete .= $requeteSQL;
	}

	//TODO: faire les requetes sur les fiches antidatées
	if (isset($_POST['perime'])&& $_POST['perime']==0) {
		//$requete .= ' AND NOT (bf_date_debut_validite_fiche<=NOW() or bf_date_debut_validite_fiche="0000-00-00") OR NOT (bf_date_fin_validite_fiche>=NOW() or bf_date_fin_validite_fiche="0000-00-00") ';
	} elseif  (isset($_POST['perime'])&& $_POST['perime']==2) {
		
	} else {
        //$requete .= ' AND (bf_date_debut_validite_fiche<=NOW() or bf_date_debut_validite_fiche="0000-00-00") AND (bf_date_fin_validite_fiche>=NOW() or bf_date_fin_validite_fiche="0000-00-00") ';
	}
	
	if ($tri == 'alphabetique') {
		$requete .= ' ORDER BY tag ASC';
	}
	else {
		$requete .= ' ORDER BY time DESC';
	}

	if ( $nb_limite!='' ) {
		$requete .= ' LIMIT 0,'.$nb_limite;
	} 
	
	//echo '<textarea style="width:100%;height:100px;">'.$requete.'</textarea>';
	return $GLOBALS['wiki']->LoadAll($requete);
}

/**
 *
 * Affiche la liste des resultats d'une recherche
 * @param $tableau_fiches : tableau de fiches provenant du resultat de la recherche
 * @param $info_nb : booleen pour afficher ou non le nombre  du resultat de la recherche (vrai par defaut)
 */
function baz_afficher_liste_resultat($tableau_fiches, $info_nb = true) {
	$res = '';
	$fiches['info_res'] = '';
	if ($info_nb) {
		$fiches['info_res'] .= '<div class="alert alert-info">'.BAZ_IL_Y_A;
		$nb_result = count($tableau_fiches);
		if ($nb_result<=1) {
			$fiches['info_res'] .= $nb_result.' '.BAZ_FICHE_CORRESPONDANTE;
		} else {
			$fiches['info_res'] .= $nb_result.' '.BAZ_FICHES_CORRESPONDANTES;
		}
		if ($nb_result>=1) {
			$fiches['info_res'] .= '. <div class="view-selector"><label>Vue : </label>
			<select class="select-view" name="view">
				<option value="liste_basique.tpl.html">Liste</option>
				<option value="liste_accordeon.tpl.html">Accord&eacute;on</option>
				<option value="liste_carte.tpl.html">Carte</option>
			</select>
			</div>'."\n";
		}
		$fiches['info_res'] .= '</div>'."\n";
	}

	$fiches['fiches'] = array();
	foreach ($tableau_fiches as $fiche) {
		$entry_values = json_decode($fiche['body'], true);
		$entry_values = array_map('utf8_decode', $entry_values);
		$entry_values['html'] = baz_voir_fiche(0, $entry_values);
		
		if (baz_a_le_droit(BAZ_ACTION_SUPPRESSION, (isset($entry_values['createur']) ? $entry_values['createur'] : ''))) {
				$url = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_SUPPRESSION.'&amp;id_fiche='.$entry_values['id_fiche']);
				$entry_values['lien_suppression'] = '<a href="'.$url.'" class="BAZ_lien_supprimer" onclick="javascript:return confirm(\''.BAZ_CONFIRM_SUPPRIMER_FICHE.' ?\');"></a>'."\n";
		}
		if (baz_a_le_droit(BAZ_ACTION_MODIFIER, (isset($entry_values['createur']) ? $entry_values['createur'] : ''))) {
				$url = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_SAISIR.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_ACTION_MODIFIER.'&amp;id_fiche='.$entry_values['id_fiche']);
				$entry_values['lien_edition'] = '<a href="'.$url.'" class="BAZ_lien_modifier"></a>'."\n";
		}
		$url = $GLOBALS['wiki']->href('', $GLOBALS['wiki']->GetPageTag(), BAZ_VARIABLE_VOIR.'='.BAZ_VOIR_CONSULTER.'&amp;'.BAZ_VARIABLE_ACTION.'='.BAZ_VOIR_FICHE.'&amp;id_fiche='.$entry_values['id_fiche']);
		$entry_values['lien_voir_titre'] = '<a href="'. $url .'" class="BAZ_lien_voir" title="Voir la fiche">'. stripslashes($entry_values['bf_titre']).'</a>'."\n".'</li>'."\n";
		$entry_values['lien_voir'] = '<a href="'. $url .'" class="BAZ_lien_voir" title="Voir la fiche"></a>'."\n".'</li>'."\n";

		$fiches['fiches'][] = $entry_values;
	}
	include_once('tools/bazar/libs/squelettephp.class.php');
	$template = (isset($_GET['field']) ? $_GET['field'] : BAZ_TEMPLATE_LISTE_DEFAUT);
	$squelcomment = new SquelettePhp('tools/bazar/presentation/templates/'.$template);
	$squelcomment->set($fiches);
	$res .= $squelcomment->analyser();

	return $res ;
}

function encoder_en_utf8($txt) {
	// Nous remplacons l'apostrophe de type RIGHT SINGLE QUOTATION MARK et les & isoles qui n'auraient pas ete remplaces par une entite HTML.
	$cp1252_map = array(
	   "\xc2\x80" => "\xe2\x82\xac", /* EURO SIGN */
	   "\xc2\x82" => "\xe2\x80\x9a", /* SINGLE LOW-9 QUOTATION MARK */
	   "\xc2\x83" => "\xc6\x92",     /* LATIN SMALL LETTER F WITH HOOK */
	   "\xc2\x84" => "\xe2\x80\x9e", /* DOUBLE LOW-9 QUOTATION MARK */
	   "\xc2\x85" => "\xe2\x80\xa6", /* HORIZONTAL ELLIPSIS */
	   "\xc2\x86" => "\xe2\x80\xa0", /* DAGGER */
	   "\xc2\x87" => "\xe2\x80\xa1", /* DOUBLE DAGGER */
	   "\xc2\x88" => "\xcb\x86",     /* MODIFIER LETTER CIRCUMFLEX ACCENT */
	   "\xc2\x89" => "\xe2\x80\xb0", /* PER MILLE SIGN */
	   "\xc2\x8a" => "\xc5\xa0",     /* LATIN CAPITAL LETTER S WITH CARON */
	   "\xc2\x8b" => "\xe2\x80\xb9", /* SINGLE LEFT-POINTING ANGLE QUOTATION */
	   "\xc2\x8c" => "\xc5\x92",     /* LATIN CAPITAL LIGATURE OE */
	   "\xc2\x8e" => "\xc5\xbd",     /* LATIN CAPITAL LETTER Z WITH CARON */
	   "\xc2\x91" => "\xe2\x80\x98", /* LEFT SINGLE QUOTATION MARK */
	   "\xc2\x92" => "\xe2\x80\x99", /* RIGHT SINGLE QUOTATION MARK */
	   "\xc2\x93" => "\xe2\x80\x9c", /* LEFT DOUBLE QUOTATION MARK */
	   "\xc2\x94" => "\xe2\x80\x9d", /* RIGHT DOUBLE QUOTATION MARK */
	   "\xc2\x95" => "\xe2\x80\xa2", /* BULLET */
	   "\xc2\x96" => "\xe2\x80\x93", /* EN DASH */
	   "\xc2\x97" => "\xe2\x80\x94", /* EM DASH */
	   "\xc2\x98" => "\xcb\x9c",     /* SMALL TILDE */
	   "\xc2\x99" => "\xe2\x84\xa2", /* TRADE MARK SIGN */
	   "\xc2\x9a" => "\xc5\xa1",     /* LATIN SMALL LETTER S WITH CARON */
	   "\xc2\x9b" => "\xe2\x80\xba", /* SINGLE RIGHT-POINTING ANGLE QUOTATION*/
	   "\xc2\x9c" => "\xc5\x93",     /* LATIN SMALL LIGATURE OE */
	   "\xc2\x9e" => "\xc5\xbe",     /* LATIN SMALL LETTER Z WITH CARON */
	   "\xc2\x9f" => "\xc5\xb8"      /* LATIN CAPITAL LETTER Y WITH DIAERESIS*/
	);
	return  strtr(preg_replace('/ \x{0026} /u', ' &#38; ', mb_convert_encoding($txt, 'UTF-8','HTML-ENTITIES')), $cp1252_map);
}

/** baz_affiche_flux_RSS() - affiche le flux rss a partir de parametres
*
*
* @return  string Le flux RSS, avec les headers et tout et tout
*/
function baz_afficher_flux_RSS() {
	if (isset($_GET['form_id'])) {
		$form_id = $_GET['form_id'];
	}
	elseif (isset($GLOBALS['_BAZAR_']['form_id'])) {
		$form_id = $GLOBALS['_BAZAR_']['form_id'];
	}
	else {
		$form_id = '';
	}

	if (isset($_GET['categorie_fiche'])) {
		$categorie_fiche = $_GET['categorie_fiche'];
	}
	elseif (isset($GLOBALS['_BAZAR_']['form_category'])) {
		$categorie_fiche = $GLOBALS['_BAZAR_']['form_category'];
	} else {
		$categorie_fiche = '';
	}

	if (isset($_GET['nbitem'])) {
		$nbitem = $_GET['nbitem'];
	}
	else {
		$nbitem = BAZ_NB_ENTREES_FLUX_RSS;
	}

	if (isset($_GET['utilisateur'])) {
		$utilisateur = $_GET['utilisateur'];
	}
	else {
		$utilisateur = '';
	}

	if (isset($_GET['statut'])) {
		$statut = $_GET['statut'];
	}
	else {
		$statut = 1;
	}

	if (isset($_GET['query'])) {
		$query = $_GET['query'];
	}
	else {
		$query = '';
	}
	$tableau_flux_rss = baz_requete_recherche_fiches($query, '', $form_id, $categorie_fiche, $statut, $utilisateur, 20);

	require_once 'XML/Util.php' ;
	// setlocale() pour avoir les formats de date valides (w3c) --julien
	setlocale(LC_TIME, "C");

	$xml = XML_Util::getXMLDeclaration('1.0', 'UTF-8', 'yes') ;
	$xml .= "\r\n  ";
	$xml .= XML_Util::createStartElement ('rss', array('version' => '2.0', 'xmlns:atom' => "http://www.w3.org/2005/Atom")) ;
	$xml .= "\r\n    ";
	$xml .= XML_Util::createStartElement ('channel');
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('title', null, utf8_encode(html_entity_decode(BAZ_DERNIERE_ACTU)));
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('link', null, utf8_encode(html_entity_decode(BAZ_RSS_ADRESSESITE)));
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('description', null, utf8_encode(html_entity_decode(BAZ_RSS_DESCRIPTIONSITE)));
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('language', null, 'fr-FR');
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('copyright', null, 'Copyright (c) '. date('Y') .' '. utf8_encode(html_entity_decode(BAZ_RSS_NOMSITE)));
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('lastBuildDate', null, strftime('%a, %d %b %Y %H:%M:%S GMT'));
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('docs', null, 'http://www.stervinou.com/projets/rss/');
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('category', null, BAZ_RSS_CATEGORIE);
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('managingEditor', null, BAZ_RSS_MANAGINGEDITOR);
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('webMaster', null, BAZ_RSS_WEBMASTER);
	$xml .= "\r\n      ";
	$xml .= XML_Util::createTag ('ttl', null, '60');
	$xml .= "\r\n      ";
	$xml .= XML_Util::createStartElement ('image');
	$xml .= "\r\n        ";
		$xml .= XML_Util::createTag ('title', null, utf8_encode(html_entity_decode(BAZ_DERNIERE_ACTU)));
		$xml .= "\r\n        ";
		$xml .= XML_Util::createTag ('url', null, BAZ_RSS_LOGOSITE);
		$xml .= "\r\n        ";
		$xml .= XML_Util::createTag ('link', null, BAZ_RSS_ADRESSESITE);
		$xml .= "\r\n      ";
	$xml .= XML_Util::createEndElement ('image');
	if (count($tableau_flux_rss) > 0) {
		// Creation des items : titre + lien + description + date de publication
		foreach ($tableau_flux_rss as $ligne) {
			$ligne = json_decode($ligne[0], true);
			$ligne = array_map('utf8_decode', $ligne);
			$xml .= "\r\n      ";
			$xml .= XML_Util::createStartElement ('item');
			$xml .= "\r\n        ";
			$xml .= XML_Util::createTag('title', null, encoder_en_utf8(html_entity_decode(stripslashes($ligne['bf_titre']))));
			$xml .= "\r\n        ";
			$lien=$GLOBALS['_BAZAR_']['url'];
			$lien->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FICHE);
			$lien->addQueryString(BAZ_VARIABLE_VOIR, BAZ_VOIR_CONSULTER);
			$lien->addQueryString('id_fiche', $ligne['id_fiche']);
			$xml .= XML_Util::createTag ('link', null, '<![CDATA['.$lien->getURL().']]>' );
			$xml .= "\r\n        ";
			$xml .= XML_Util::createTag ('guid', null, '<![CDATA['.$lien->getURL().']]>' );
			$xml .= "\r\n        ";
			$tab = explode("wakka.php?wiki=",$lien->getURL());
			$xml .= XML_Util::createTag ('description', null, '<![CDATA['.encoder_en_utf8(html_entity_decode(baz_voir_fiche(0, $ligne))).']]>' );
			$xml .= "\r\n        ";
			if ($ligne['date_debut_validite_fiche'] != '0000-00-00' &&
			$ligne['date_debut_validite_fiche']>$ligne['date_creation_fiche']) {
				$date_pub =  $ligne['date_debut_validite_fiche'];
			} else $date_pub = $ligne['date_creation_fiche'] ;
			$xml .= XML_Util::createTag ('pubDate', null, strftime('%a, %d %b %Y %H:%M:%S GMT',strtotime($date_pub)));
			$xml .= "\r\n      ";
			$xml .= XML_Util::createEndElement ('item');
		}
	}
	else {//pas d'annonces
		$xml .= "\r\n      ";
		$xml .= XML_Util::createStartElement ('item');
		$xml .= "\r\n          ";
		$xml .= XML_Util::createTag ('title', null, utf8_encode(html_entity_decode(BAZ_PAS_DE_FICHES)));
		$xml .= "\r\n          ";
		$xml .= XML_Util::createTag ('link', null, '<![CDATA['.$GLOBALS['_BAZAR_']['url']->getUrl().']]>' );
		$xml .= "\r\n          ";
		$xml .= XML_Util::createTag ('guid', null, '<![CDATA['.$GLOBALS['_BAZAR_']['url']->getUrl().']]>' );
		$xml .= "\r\n          ";
		$xml .= XML_Util::createTag ('description', null, utf8_encode(html_entity_decode(BAZ_PAS_DE_FICHES)));
		$xml .= "\r\n          ";
		$xml .= XML_Util::createTag ('pubDate', null, strftime('%a, %d %b %Y %H:%M:%S GMT',strtotime("01/01/%Y")));
		$xml .= "\r\n      ";
		$xml .= XML_Util::createEndElement ('item');
	}
	$xml .= "\r\n    ";
	$GLOBALS['_BAZAR_']['url']->addQueryString(BAZ_VARIABLE_ACTION, BAZ_VOIR_FLUX_RSS);
//	$xml .= utf8_encode(html_entity_decode('<atom:link href="'.$GLOBALS['_BAZAR_']['url']->getUrl().'" rel="self" type="application/rss+xml" />'."\r\n  "));
	$xml .= XML_Util::createEndElement ('channel');
	$xml .= "\r\n  ";
	$xml .= XML_Util::createEndElement('rss') ;

	// Nettoyage de l'url
	$GLOBALS['_BAZAR_']['url']->removeQueryString(BAZ_VARIABLE_ACTION);
	$GLOBALS['_BAZAR_']['url']->removeQueryString('id_fiche');


	echo html_entity_decode($xml);

}


?>
