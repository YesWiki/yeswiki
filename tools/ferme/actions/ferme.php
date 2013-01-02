<?php
	if (!defined("WIKINI_VERSION")) {
        die ("acc&egrave;s direct interdit");
	}


	error_reporting(E_ALL); 

	include_once("tools/ferme/libs/model.php");
	include_once("tools/ferme/libs/view.php");
	
	

	$farm = new Farm("tools/ferme/config/ferme.config.php");
	$view = new FarmView($farm);

	//test des alertes

	if (isset($_POST['action']) && isset($_POST['wikiName'])) {
		$pasderreur = true;
		try {
			$wikiPath = $farm->installWiki($_POST['wikiName'], $_POST['mail'], $_POST['description']);
		} catch(Exception $e){
			$view->addAlerte($e->getMessage());
			$pasderreur = false;
			//die();
		}

		/*********************************************************************
		 * Envois email.
		 ********************************************************************/
		if ($pasderreur) {
			mail($_POST["mail"], 
				"Création du wiki ".$_POST["wikiName"], 
				"Bonjour, 

		Votre wiki : ".$_POST["wikiName"]." a été créé avec succès. 
		Vous le trouverez a l'adresse : ".$farm->config["base_url"].$wikiPath."

		Pour toute information complémentaire n'hésitez pas à contacter :
		 - christian.resche@supagro.inra.fr
		 - florestan.bredow@supagro.inra.fr

		 Cordialement.",
 				'From: no-reply@cdrflorac.fr' . "\r\n" );
 		/********************************************************************/


			$view->addAlerte('<a href="'.$farm->config["base_url"].$wikiPath.'">Visiter le nouveau wiki</a>');
		}
	}
	


	$view->showNewWiki();


?>
