<?php
class FarmView{
	
	protected $farm;
	protected $alertes;

	/************************************************************************
	 * Constructeur
	 ***********************************************************************/
	function __construct($farm){
		$this->farm = $farm;
		$alertes = array();
	}
	
	/************************************************************************
	 * Affiche le wiki.
	 ***********************************************************************/
	//TODO : Ajouter choix du template
	function showNewWiki($wikiName = "", $mail = "", $description = ""){
		$template = "tools/ferme/presentation/templates/".$this->farm->config['template'];

		if(!is_file($template)) {
			die("Template introuvable. (tools/ferme/presentation/templates/".$this->farm->config['template'].").");
		}
		include("tools/ferme/presentation/templates/".$this->farm->config['template']);	
	}

	
	/************************************************************************
	 * Affiche la liste des Themes selon le template fournis
	 ***********************************************************************/
	function printThemesList($template = "theme.phtml"){
		$themesList = $this->farm->getThemesList();
		$i = 0;
		foreach ($themesList as $theme) {
			include("tools/ferme/presentation/templates/".$template);
		}
		unset($themesList);
	}

	/************************************************************************
	 * Affiche la liste des wikis selon le template fournis 
	 * et l'ordre demandé
	 ***********************************************************************/
	function printWikisList($order = 'none', $template = "wiki.phtml"){
		$listWikis = $this->farm->getWikisList('name');
		include("tools/ferme/presentation/templates/".$template);
		unset($wiki);
	}


	/************************************************************************
	 * Affiche la liste des alertes selon le template fournis.
	 ***********************************************************************/
	function printAlertesList($template = "alerte.phtml"){
		//Affichage des alertes
		if(!empty($this->alertes)){
			$i = 0;
			foreach ($this->alertes as $alerte)
				$id = "alerte".$i; 
				include("tools/ferme/presentation/templates/".$template);
				$i++;
			unset($alerte);
		}
	}

	/************************************************************************
	 * HASH-CASH : Charge le JavaScript qui génére la clé.
	 ***********************************************************************/
	function HashCash(){	
		
		//TODO : Rendre ce code "portable"
		echo '<!--Protection HashCash -->
		<script type="text/javascript" 
				src="'.$this->farm->config['base_url'].'tools/ferme/libs/wp-hashcash-js.php?siteurl='.$this->farm->config['base_url'].'">
		</script>';
	}

	/************************************************************************
	 * Ajoute une alerte a afficher.
	 ***********************************************************************/
	//TODO : Gérer les alertes dans le model.
	function addAlerte($text){
		$this->alertes[] = $text;
	}

	/***********************************************************************
	 * Envois un email de confirmation
	 **********************************************************************/
	//TODO : ne valider l'envois que si le paramêtre mail est a 1 dans la 
	// configuration.
	function sendConfirmationMail($mail, $wikiName){

	}
	
}


?>
