<?php
/*
savevote.php

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


header('Content-type: application/json; charset=UTF-8');
//teste l'existance du vote
$sql = 'SELECT id, value FROM ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'WHERE resource = "' . $this->GetPageTag() . '" '
			. 'AND property = "http://outils-reseaux.org/_vocabulary/vote" '
			. 'AND value LIKE \'%"id":"'.$_POST['id'].'"%\' ';

$results = $this->LoadAll($sql);

if ($results) {
	$sql = 'UPDATE ' . $this->GetConfigValue('table_prefix') . 'triples '
			. 'SET value="'.addslashes(json_encode($_POST)).'" '
			. 'WHERE resource = "' . $this->GetPageTag() . '" '
			. 'AND property = "http://outils-reseaux.org/_vocabulary/vote" '
			. 'AND value LIKE \'%"id":"'.$_POST['id'].'"%\' ';

	$this->Query($sql);
	echo json_encode(array('message'=>'donnees existantes'));
}
else {
	$GLOBALS["wiki"]->InsertTriple($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/vote', json_encode($_POST), '', '');
	echo json_encode(array('message'=>'pas de donnees'));	
}

?>

