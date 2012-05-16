<?php

	//----->Se connecte à la base php
	function BasePerudo(){
		$base = mysql_connect ('localhost', 'root', '');
		mysql_select_db ('Perudo', $base) ;
	}
	
	//---->lance n<=5 dés et range les valeurs dans un tableau
	function roll_dice($nb_dice) {
		for ($i=1;$i<=$nb_dice;$i++) {
			$tabvaluedice[$i]=rand(1,6);
		}			
		return $tabvaluedice;
	}
	
	//A QUI est-ce de jouer ?
	function a_qui_de_jouer ($ancienjoueur, $rejoue){	
		/*Si le dernier joueur a avoir fait une enchere s'est trompé 
		mais a encore des dés alors c'est à lui de jouer*/
		if ($rejoue and $_SESSION['joueur'.$ancienjoueur]['nb_dice']!=0) {
			$numerojoueur=$ancienjoueur;
		}
		//Sinon on avance au joueur suivant
		else {
			//Si on arrive au dernier joueur on repasse au premier joueur ayant encore des dés.
			if ($ancienjoueur==$_SESSION['nbjoueur']) {
				$t=1;
				while ($_SESSION['joueur'.$t]['nb_dice']==0){
					$t++;
				}
				$numerojoueur=$t;
			}
			//Sinon on passe au joueur suivant ayant encore des dés.
			else {
				$t=$ancienjoueur+1;
				while ($_SESSION['joueur'.$t]['nb_dice']==0){
					if ($t==$_SESSION['nbjoueur']) {
						$t=0;
					}
					$t++;
				}
				$numerojoueur=$t;
			}
		}
		$tbl_a_qui_de_jouer = array ($numerojoueur, $rejoue);
	return $tbl_a_qui_de_jouer;
	}
	
	//Enregistre une enchère standard dans la session.
	function bet_record ($ancienjoueur, $valeureenchere) {
		$_SESSION['joueur'.$ancienjoueur]['bet_value']=explode ("-", $valeureenchere);
		$_SESSION['joueur'.$ancienjoueur]['bet_value'][0]=intval ($_SESSION['joueur'.$ancienjoueur]['bet_value'][0]);
		$_SESSION['joueur'.$ancienjoueur]['bet_value'][1]=intval ($_SESSION['joueur'.$ancienjoueur]['bet_value'][1]);
		
	}

	//TO DO : compact 2 function comptage_des
	//renvoie le nombre d'occurence total du dé portant la valeur de la dernière annonce
	function comptage_des ($dice_value,$tbl_session) {
		$total=0;
		for ($i=1;$i<=$tbl_session['nbjoueur'];$i++) {
			$tableau_decompte_des = array_count_values($tbl_session['joueur'.$i]['dice_value']);
			if(isset($tableau_decompte_des[$dice_value])){
				$total=$total+$tableau_decompte_des[$dice_value];
			}
		}
		return $total;
	}
	
	//renvoie le nombre d'occurence de chaque valeur des dés du bot
	function comptage_des_bot ($dice_value,$tbl_session,$numerojoueur) {
		$total=0;
		$tableau_decompte_des = array_count_values($tbl_session['joueur'.$numerojoueur]['dice_value']);
		if(isset($tableau_decompte_des[$dice_value])){
		$total=$total+$tableau_decompte_des[$dice_value];	
		}
		return $total;
	}
	
	//TODO : si l'enchère suivante est en pécos, l'afficher.
	//Détermine la valeur minimale de l'enchère suivante.
	function enchere_suivante ($bet_dice, $bet_value) {	
		$enchereminimalpecos=0;
		//Si la valeur de l'enchère vaut 6, on doit passer au palier suivant.
		if ($bet_value==6) {
			$bet_dice=$bet_dice+1;
			$bet_value=2;
		}
		/*Si la valeur est 1 (ou Pécos), 
		alors l'enchère suivante minimale est plus haute.
		Et on initialise l'enchère minimale en Pécos*/
		elseif ($bet_value==1){
			$enchereminimalpecos=$bet_dice+1;
			$bet_dice=2*$bet_dice+1;
			$bet_value=2;
		}

		elseif ($bet_dice==0 and $bet_value==0) {
			$bet_dice=1; 
			$bet_value=2;
		}
		//Sinon la valeur du dé augmente de 1.
		else {
			$bet_value=$bet_value+1;
		}
		$enchere_suivante = array ($bet_dice,$bet_value,$enchereminimalpecos);
		return ($enchere_suivante);
	}
	//return $nb^$exponent
	function exponent ($nb, $exponent){
		if ($exponent==0) {
		$total=1;
		}
		else {
		for ($total = $nb; $exponent > 1; $exponent--)
		$total = $total * $nb;
		}
		return ($total); 
	}
	/*Calcule la probabilité d'une enchère
	$joker est vrai si on doit compter les pécos en plus des autres dés.*/
	function proba_annonce ($ndtotal,$ndmin,$joker,$ordre_enchere,$nd,$vd) { 
	//Si le joueur a déjà tout ce qu'il cherche dans sa main alors la proba est 1
	//TODO : enlever proba_annonce = 0 & = 1.
	if ($ndmin<=0) {
		$proba_annonce=1;
	}
	//Sinon Si le nombre de dés total en jeu est supérieur au nombre de dés cherchés	
	elseif (($ndtotal-$ndmin)>=0){
		if ($joker) {
		$proba_annonce = 1 - (exponent(4,($ndtotal-$ndmin+1)) * exponent (6,($ndmin-1)))/exponent (6,$ndtotal);
		}
		else {
		$proba_annonce = 1 - (exponent(5,($ndtotal-$ndmin+1)) * exponent (6,($ndmin-1)))/exponent (6,$ndtotal);		
		}
	}
	//Sinon la probabilité est de 0 
	else {
		$proba_annonce=0;
	}
	$tbl_enchere[$ordre_enchere] = $proba_annonce;
	return $tbl_enchere[$ordre_enchere];
}		

	
	//memorise la main d'un joueur dans la table 'des'
	//-----------------------------> A faire pour tous les joueurs <------------------------
	function memorize_hand($memorize){
		BasePerudo();
		$sql = 'UPDATE tbl_dice SET Dice_val='.$memorize.' WHERE Id_player=1';
		mysql_query ($sql) or die ('Erreur SQL !'.$sql.'<br />'.mysql_error());
		mysql_close();
	}
	//Attribue une id a un nouveau joueur
	/*function attribue_id(){
		BasePerudo();
		$sql = 'INSERT INTO tbl_dice SET Dice_value='.$memorize.' WHERE Id_player=1';
		mysql_query ($sql) or die ('Erreur SQL !'.$sql.'<br />'.mysql_error());
		mysql_close();
	}*/
	/*//Atribue une table aux joueurs (maximum 6 joueurs)
	function attribue_table($joueur1, $joueur2, $joueur3, $joueur4, $joueur5, $joueur6) {
		INSERT INTO TABLE tbl_playground
		(	Id_tbl, Id_player1, Id_player2, Id_player3, Id_player4, Id_player5, Id_player6)
		VALUES
		(	'',		$joueur1,	$joueur2,	$joueur3,	$joueur4,	$joueur5,	$joueur6)	
		
	}*/

	
	
	?>
