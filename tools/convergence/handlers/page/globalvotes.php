<?php
/*
globalvotes.php

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

$id = session_id();
$decroissance = false;


// on récupère les informations sur le vote : titre et dernière MAJ
$resultsparam = $this->GetTripleValue($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/voteconfig', '', '');
if ($resultsparam) {
	$resultsparam = json_decode($resultsparam, true);
	$global['title'] = $resultsparam['title'];
	$global['lastupdate'] = (isset($resultsparam['lastupdate']) ? $resultsparam['lastupdate'] : '0' );
} 

// on met les valeurs par défaut sinon TODO : mettre à jour le triple de vote
else {
	$global['title'] = 'Vote Convergence';
	$global['lastupdate'] = '0';
}

$results = $this->GetAllTriplesValues($this->GetPageTag(), 'http://outils-reseaux.org/_vocabulary/vote', '', '');

if ($results) {
	$global['nbvote'] = count($results);
	$globalup = 0;
	$globaldown = 0;
	$globalright = 0;
	foreach($results as $votant) {
		$vote = json_decode($votant['value'], true);

		// on applique la décroissance globale		
		if ($_POST['evolution'] != '100' && isset($_POST['cooldowntime']) && (time() - $global['lastupdate'] >= $_POST['cooldowntime']/1000) ) {

			// calcul de la décroissance ou qui sait croissance!
			$vote['up'] = $vote['up']*$_POST['evolution']/100;
			$vote['down'] = $vote['down']*$_POST['evolution']/100;
			$vote['right'] = $vote['right']*$_POST['evolution']/100;
			
			//modif ed de bourrin : minima pour bien lisible TODO : pb du votant inactif 
			if ($vote['up'] <= 7) {$vote['up']=7;}
			if ($vote['down'] <= 7) {$vote['down']=7;}			
			if ($vote['right'] <= 7) {$vote['right']=7;}			
			//on déterminer le nb d'inactifs
			if ($vote['up'] <= 7 AND $vote['down'] <= 7 AND $vote['right'] <= 7) {
				if (isset($nbvotantinactif)) {
					$nbvotantinactif = $nbvotantinactif+1; 
				} 
				else {
					$nbvotantinactif = 1;
				}
			}
			
			// on modifie les valeurs du vote d'un votant subissant la décroissance
			$sql = 'UPDATE ' . $this->GetConfigValue('table_prefix') . 'triples '
				. 'SET value="'.addslashes(json_encode($vote)).'" '
				. 'WHERE resource = "' . $this->GetPageTag() . '" '
				. 'AND property = "http://outils-reseaux.org/_vocabulary/vote" '
				. 'AND value LIKE \'%"id":"'.$vote['id'].'"%\' ';

			$this->Query($sql);
			
			$decroissance = true;
		
		}
		
		// pour le vote de la personne qui a enclenché le globalvote, on lui ramene son vote
		if ($vote['id'] == $id) {
			$global['uservoteup'] = $vote['up'];
			$global['uservotedown'] = $vote['down'];
			$global['uservoteright'] = $vote['right'];
		} 
			
		
		//on augmente la patate globale de façon recurrente	
		$globalup = $globalup + $vote['up'];
		$globaldown = $globaldown + $vote['down'];
		$globalright = $globalright + $vote['right'];

	}
	
	// comme la base a étée sauvée, on actualise la date du lastupdate
	if ($decroissance) {
		$sql = 'UPDATE ' . $this->GetConfigValue('table_prefix') . 'triples '
				. 'SET value="'.addslashes(json_encode(array('title' => $global['title'], 'lastupdate' => time() ))).'" '
				. 'WHERE resource = "' . $this->GetPageTag() . '" '
				. 'AND property = "http://outils-reseaux.org/_vocabulary/voteconfig"';
		$this->Query($sql);	
	}
	
	// Si on n'a pas trouve de vote pour la personne qui a enclenché le globalvote, on lui mets les valeurs d'un nouveau votant
	if (!isset($global['uservoteup'])) {
		$global['uservoteup'] = 20;
		$global['uservotedown'] = 20;
		$global['uservoteright'] = 20;
	}
	
	// on détermine la patate globale moyenne
	$global['globalup'] = $globalup/$global['nbvote'];
	$global['globaldown'] = $globaldown/$global['nbvote'];
	$global['globalright'] = $globalright/$global['nbvote'];
	
}

// Si aucun vote n'a été sauvegardé, on met les valeurs par défaut pour tout
else {
	$global['nbvote'] = 0;
	$global['globalup'] = 48;
	$global['globaldown'] = 52;
	$global['globalright'] = 52;
	$global['uservoteup'] = 48;
	$global['uservotedown'] = 52;
	$global['uservoteright'] = 52;
}
echo json_encode($global);
?>

