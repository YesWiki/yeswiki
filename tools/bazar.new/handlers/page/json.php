<?php
/*
json.php

Copyright 2010  Florian Schmitt <florian@outils-reseaux.org>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}


if (isset($_REQUEST['demand'])) {
	header('Content-type: application/json; charset=UTF-8');
	
	switch ($_REQUEST['demand']) {
		//les listes bazar
		case "listes":
			if (baz_a_le_droit($_REQUEST['demand'])) {
				echo json_encode(baz_valeurs_toutes_les_listes('json'));
			}			
		    break;
		    
		//les formulaires bazar
		case "formulaires":
			if (baz_a_le_droit($_REQUEST['demand'])) {
				echo json_encode(baz_valeurs_tous_les_formulaires(NULL, 'json'));
			}			
		    break;
		
		//les listes et formulaires bazar
		case "listes_et_formulaires":
			if (baz_a_le_droit($_REQUEST['demand'])) {
				$tab['listes'] = baz_valeurs_toutes_les_listes('json');
				$tab['fiches'] = baz_valeurs_tous_les_formulaires(NULL, 'json');
				echo json_encode($tab);
			}			
		    break;
		    
		//les fiches bazar
		case "fiches":
			//TODO : warning sur les isset
			/*$id_form = ((isset($_POST['id_formulaire'])) ? $_POST['id_formulaire'] : '');
			$categorie = ((isset($_REQUEST['categorie'])) ? $_REQUEST['categorie'] : '');*/
			$tab = baz_requete_recherche_fiches('', 'alphabetique', $id_form, $categorie);
			echo json_encode($tab);
		    break;
		   
		//les pages wiki
		case "pages":
			echo json_encode($this->LoadAllPages());
		    break;
		    
		//les commentaires wiki
		case "comments":
			echo json_encode($this->LoadRecentComments());
		    break;		
	}
}
?>

