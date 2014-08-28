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
		// les listes bazar
		case "lists":
			$list = (isset($_REQUEST['list']) ? $_REQUEST['list'] : ''); 
			echo json_encode(baz_utf8_encode_recursive(baz_valeurs_liste($list)));		
		    break;
		    
		// les formulaires bazar
		case "forms":
			$form = (isset($_REQUEST['form']) ? $_REQUEST['form'] : '');
			echo json_encode(baz_utf8_encode_recursive(baz_valeurs_formulaire($form)));			
		    break;
		    
		// les fiches bazar
		case "entries":
			$page = (isset($_REQUEST['page']) ? $_REQUEST['page'] : '');
			$form = (isset($_REQUEST['form']) ? $_REQUEST['form'] : '');
			$tags = (isset($_REQUEST['tags']) ? $_REQUEST['tags'] : '');
			$order = (isset($_REQUEST['order']) && $_REQUEST['order'] == 'alphabetique' ? $_REQUEST['order'] : '');
			$results = baz_requete_recherche_fiches('', $order, $form, '', 1, '', '');
			foreach ($results as $wikipage) {
    			$decoded_entrie = json_decode($wikipage['body'], true);  //json = norme d'ecriture utilisée pour les fiches bazar (en utf8)
    			$tab_entries[$decoded_entrie['id_fiche']] = $decoded_entrie;
    		}
			echo json_encode( $tab_entries );
		    break;
		   
		// les pages wiki
		case "pages":
			echo json_encode(baz_utf8_encode_recursive($this->LoadAllPages()));
		    break;
		    
		// les commentaires wiki
		case "comments":
			echo json_encode(baz_utf8_encode_recursive($this->LoadRecentComments()));
		    break;		
	}
}
?>

