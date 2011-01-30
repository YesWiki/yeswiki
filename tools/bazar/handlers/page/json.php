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
		case "listes_et_fiches":
			if (baz_a_le_droit($_REQUEST['demand'])) {
				$tab['listes'] = baz_valeurs_toutes_les_listes('json');
				$tab['fiches'] = baz_valeurs_tous_les_formulaires('toutes', 'json');
				echo json_encode($tab);
			}			
		    break;
	}
}
?>

