<?php

	//session_start();

	//fonctions mathématiques de perudo
	include_once ('tools/perudo/libs/fonction.php');
	
	//librairie du générateur de template
	include_once ('tools/perudo/libs/hyla_tpl.class.php');
	
	// Gestion de la langue, seul francais pour l'instant (TODO: internationalisation)
	include_once ('tools/perudo/actions/lang/perudo-fr.php');
	
	class lang {
		var $game_title;
		var $game_desc;
		var $welcome_message;
		var $start_game;
		var $next_turn;
	}

	$lang = new lang;
	$lang->page_title = PAGE_TITLE;
	$lang->game_desc = GAME_DESC;
	$lang->game_title = GAME_TITLE;
	$lang->start_game = START_BUTTON;
	$lang->next_turn = NEXT_TURN;
	
	$lang->enter_game_form_choose_total_player_nb = ENTER_GAME_FORM_CHOOSE_TOTAL_PLAYER_NB;
	$lang->enter_game_form_player = ENTER_GAME_FORM_PLAYER;
	$lang->enter_game_form_choose_the_computer_nb = ENTER_GAME_FORM_CHOOSE_THE_COMPUTER_NB;
	$lang->enter_game_form_computer_player = ENTER_GAME_FORM_COMPUTER_PLAYER;
	
	$lang->player_name = PLAYER_NAME;
	$lang->your_dices = YOUR_DICES;
	
	$lang->players_still_active = PLAYERS_STILL_ACTIVE;
	$lang->totaldices = TOTALDICES;
	$lang->player_turn = PLAYER_TURN;
	$lang->have_to_play = HAVE_TO_PLAY;
	
	$lang->playr_had_said = PLAYR_HAD_SAID;
	$lang->dices = DICES;
	$lang->remaining_dices = REMAINING_DICES;
	
	$lang->outbid = OUTBID;
	
	$lang->dices_of_everyone = DICES_OF_EVERYONE;
	
	class form_link {
		var $url;
	}
	$formlink = new form_link;
	$formlink->url = $this->href();
		
	class form_dudo_or_calza {
		var $ancienjoueur;
		var $joueurencours;
	}
		
	class prevent_refresh {
		var $ancienjoueur;
		var $joueurencours;
		var $rejoue;
		var $joueurpalifico;
	}
	class form_new_name {
		var $numerojoueur;
	}
	class game_global_inf {
		var $totalplayer;
		var $totaldices;	
	}
	class current_turn {
		var $playername;
	}	
	class bid_form {
		var $tourpalifico;
		var $numerojoueur;
	}	
	class previous_turn {
		var $playername;	
		var $playerbidnb;	
		var $playerbidvalue;
		var $playerdicenb;	
	}
	class bid_resume_end_turn {
		var $lastplayername;
		var $lastplayerbidnb;
		var $lastplayerbidvalue;
		var $playername;
		var $enchere;
	}
	
	class each_playr_bid_end_turn {
		var $nomdujoueur;
	}
		
	//variables générales de l'interface de perudo
	$deroule = ''; 
	$interaction = '';
	$form_new_game = '';
	//Si un joueur a cliqué sur nouvelle partie on détruit la session précédente.
	if (isset($_POST['newgame'])) {
		$_SESSION=null;
		session_destroy();	
	}

	//Si le nombre de joueur vient d'être choisi, on initialise la session.
	if (isset($_POST['nbjoueur'])){
		$_SESSION['nbjoueur']=$_POST['nbjoueur'];
		$_SESSION['nbjoueuractif']=$_POST['nbjoueur'];
		$_SESSION['nbdestotal']=5*$_SESSION['nbjoueur'];
		$_SESSION['bot']['actif']=false;
		//Si il y a au moins 1 bot on l'enregistre dans la session.
		if ($_POST['nbbot']!=0){
			$_SESSION['bot']['actif']=true;
			$_SESSION['nbbot']=$_POST['nbbot'];		
			/*Les bots sont placés après les joueurs humains, 
			prennent des noms et sont identifié comme bot.*/
			for ($i=(1+$_SESSION['nbjoueur']-$_SESSION['nbbot']);$i<=$_SESSION['nbjoueur'];$i++){
				$_SESSION['joueur'.$i]['nom']='SuperBot n° '.$i;
			}
		}
		//Pour chaque joueur on initialise le nombre de dés et on les lance.
		for ($n=1;$n<=$_SESSION['nbjoueur'];$n++) {
				$_SESSION['joueur'.$n]['nb_dice']=5;
				$_SESSION['joueur'.$n]['dice_value']=roll_dice(5);
		}
	}

	/* Choisir le nombre de joueur : 
	si le nb de joueur n'est pas enregistré dans la session, on le choisit dans le menu déroulant.*/
	if (!isset($_SESSION['nbjoueur'])) {
		// Formulaire de choix des joueurs pour une nouvelle partie
		$tplformnewgame = new Hyla_Tpl('tools/perudo/presentation/templates');
		$tplformnewgame->importFile('tplformnewgame', 'form.tpl.html');
		$tplformnewgame->setVar('lang', $lang);
		$tplformnewgame->setVar('formlink', $formlink);
		$interaction .= $tplformnewgame->render();
	}
	/*---------La partie peut commencer-------------------------------------------------------------------
	-----------on connait le nombre de joueur, et chaque joueur a lancé ses 5 dés.----------------------*/
	else {
		$_SESSION['joueurpalifico']=false;
		//Si le nom proposé N'est PAS vide, on l'enregistre dans la session.
		if (isset($_POST['nom']) AND $_POST['nom']!='') {
			$_SESSION['joueur'.$_POST['ancienjoueur']]['nom']=$_POST['nom'];
			$_SESSION['formulaire_joueur_appelle']=true;
		}
		//Si une enchère Dudo ou Calza vient d'être faite, on l'enregistre dans la session:
		if (isset($_POST['enchere']) AND ($_POST['enchere']=='Dudo' OR $_POST['enchere']=='Calza')) {
			$_SESSION['enchere']=$_POST['enchere'];
			$_SESSION['joueurencours']=$_POST['joueurencours'];
			$_SESSION['ancienjoueur']=$_POST['ancienjoueur'];
		}
		//Si une enchère standard vient d'être faite, on l'enregistre dans la session.
		if (isset($_POST['valeureenchere'])) {
			$_SESSION['valeureenchere']=$_POST['valeureenchere'];
			$_SESSION['ancienjoueur']=$_POST['ancienjoueur'];
			$_SESSION['enchere']=$_POST['enchere'];					
		}
		//Si on est dans un tour palifico, on l'enregistre dans la session.
		if (isset($_POST['tourpalifico'])) {
			$_SESSION['tourpalifico']=$_POST['tourpalifico'];
		}
		//Si un nouveau tour commence, on enregistre les paramètres du nouveau tour dans la session
		if (isset($_POST['nouveautour'])) {
			$_SESSION['numerojoueur']=$_POST['ancienjoueur'];
			$_SESSION['numerojoueur']=$_POST['joueurencours'];	

		}
		//Si un joueur est palifico on l'enregistre dans la session
		if (isset($_POST['joueurpalifico'])) {
			$_SESSION['joueurpalifico']=$_POST['joueurpalifico'];
		}
		//Si un joueur doit rejouer on l'enregistre dans la session
		if (isset($_POST['rejoue'])) {
			$_SESSION['rejoue']=$_POST['rejoue'];
		}
		else {$_SESSION['rejoue']=false;}
				
		//S'il n'y a qu'un seul joueur actif, il a gagné.
		if($_SESSION['nbjoueuractif']==1){
			//Qui a gagné ?
			for ($n=1;$n<=$_SESSION['nbjoueur'];$n++){
				if ($_SESSION['joueur'.$n]['nb_dice']!=0){
					$deroule .= VICTORY.$_SESSION['joueur'.$n]['nom'];
				}
			}
			$_SESSION=null; 
			session_destroy();	
		}
		//Sinon il y a plusieurs joueurs avec des dés
		else {
			//Si une enchère NORMALE (ni Dudo ni Calza) vient d'être donné il faut l'enregistrer dans la session.
			/*TODO : passer les paramètres d'enchères au bot : 
			qui, quelle enchère*/
			if (isset($_POST['valeureenchere'])) {
			bet_record ($_POST['ancienjoueur'], $_POST['valeureenchere']);
			}
			/*--------------------------A Qui est-ce de jouer ? ------------------------------------------
			Si un joueur vient de jouer, c'est au suivant*/
			if (isset ($_POST['ancienjoueur'])){
				if (!isset($_POST['rejoue'])) {
					$_SESSION['rejoue']=false;
				}
				else {$_SESSION['rejoue']=$_POST['rejoue'];}
				//Si le joueur vient d'entrer son nom dans le formulaire_joueur,
				if (isset($_POST['nom'])){
					//Il vient juste d'entrer son nom, c'est donc à lui de jouer
					$_SESSION['numerojoueur']=$_POST['ancienjoueur'];
				}
				else {
					$tbl_a_qui_de_jouer=a_qui_de_jouer ($_SESSION['ancienjoueur'], $_SESSION['rejoue']);
					$_SESSION['numerojoueur']=$tbl_a_qui_de_jouer[0];
					$_SESSION['rejoue']=$tbl_a_qui_de_jouer[1];
				}
				$_SESSION['ancienjoueur']=$_POST['ancienjoueur'];
			}
			//Sinon c'est le tout PREMIER TOUR de la partie
			else {
				$_SESSION['numerojoueur']=1;
			}
			/*----------------------Phase de jeu principale-----------------------------------------------
			Si les joueurs N'ont PAS de dés lancés, on lance les dés pour tout le monde.*/
			$anyonewithdice=1;
			while (!isset($_SESSION['joueur'.$anyonewithdice]['dice_value'][1])){
				$anyonewithdice++;
				if (!isset($_SESSION['joueur'.$anyonewithdice]['dice_value'][1])) {
					//Si un joueur N'a PLUS de dés il ne lance plus de dés.
					for ($n=1;$n<=$_SESSION['nbjoueur'];$n++) {
						if ($_SESSION['joueur'.$n]['nb_dice']==!0) {
							$_SESSION['joueur'.$n]['dice_value']=roll_dice($_SESSION['joueur'.$n]['nb_dice']);
						}
					}
				}			
			}
			//Si le joueur en cours N'a PAS de nom, il en choisit un.
			if (!isset($_SESSION['joueur'.$_SESSION['numerojoueur']]['nom'])) {
								
				$tplformnewname = new Hyla_Tpl('tools/perudo/presentation/templates');
				$tplformnewname->importFile('tplformnewname', 'enter_name_form.tpl.html');
				$tplformnewname->setVar('lang', $lang);
				$tplformnewname->setVar('formlink', $formlink);
				$tplformnewname->setVar('numerojoueur', $_SESSION['numerojoueur']);
								
				$deroule .= $tplformnewname->render();
			}	
			
			/*Cheat F 5
			Sinon SI une enchère DUDO ou CALZA a été passée dans un fomulaire POST
			mais que la valeur de l'enchère n'est pas connue, 
			- car toutes les enchère des joueurs ont été effacées 
			lorsqu'un joueur a cliqué sur dudo ou calza -
			alors c'est du cheat avec F5
			.*/
			elseif (isset($_POST['ancienjoueur']) AND !isset($_SESSION['joueur'.$_POST['ancienjoueur']]['bet_value'][1]) AND isset($_POST['enchere']) AND ($_POST['enchere']=='Calza' OR $_POST['enchere']=='Dudo')) {
				if (!isset($_POST['joueurpalifico'])) {
					$_POST['joueurpalifico']=false;
				}
				if (!isset($_POST['rejoue'])) {
					$_POST['rejoue']=false;
				}
				
				$prevent_refresh = new prevent_refresh;
				$prevent_refresh->ancienjoueur = $_POST['ancienjoueur'];
				$prevent_refresh->joueurencours = $_POST['joueurencours'];
				$prevent_refresh->rejoue = $_SESSION['rejoue'];
				$prevent_refresh->joueurpalifico = $_POST['joueurpalifico'];

				$tplpreventrefresh = new Hyla_Tpl('tools/perudo/presentation/templates');
				$tplpreventrefresh->importFile('tplpreventrefresh', 'prevent_refresh_form.tpl.html');
				$tplpreventrefresh->setVar('prevent_refresh', $prevent_refresh);
				$tplpreventrefresh->setVar('formlink', $formlink);
				$tplpreventrefresh->setVar('lang', $lang);
				
				$deroule .= $tplpreventrefresh->render();

			}
			//--------------Sinon une enchère peut être annoncée---------------------------------
			else {
				//L'enchere proposee est standard
				//Si un joueur vient d'entrer son nom, on rectifie la position d'ancien joueur.
				if (isset($_SESSION['formulaire_joueur_appelle'])){
					$_SESSION['ancienjoueur']=$_SESSION['ancienjoueur']-1;
				}
//--------------Si c'est au BOT de jouer, il propose une enchère------------------------------------------
				//TODO : tant que le joueur est un bot.
				//Si c'est un bot il fait une enchère auto
				if ($_SESSION['bot']['actif']==true AND $_SESSION['joueur'.$_SESSION['numerojoueur']]['nom']=='SuperBot n° '.$_SESSION['numerojoueur']) {
					//Si c'est la PREMIERE enchère--------------------------------------------------------
					if (!isset($_SESSION['enchere'])) {
						//Si le bot est Palifico
						if(isset($_SESSION['joueurpalifico']) AND $_SESSION['joueurpalifico']==true) {
							//Le bot a 67% de chance de bluffer le palifico
							if (rand(1,100)>=33){
								$choix=true;
							}
							else {
								$choix=false;
							}
							//Si il bluff
							if ($choix){
								$vd = rand(1,6);
							}								
							//Sinon il ne bluff pas
							else{
								$vd = $_SESSION['joueur'.$_SESSION['numerojoueur']]['dice_value'][1];
							}
							//S'il y plus de 17 dés et plus de 3 joueurs actifs
							if ($_SESSION['nbdestotal']>17 AND $_SESSION['nbjoueuractif']>3) {
								$_SESSION['valeureenchere'] = '4-'.$vd;
							}
							//S'il y plus de 12 dés et plus de 3 joueurs actifs
							if ($_SESSION['nbdestotal']>12 AND $_SESSION['nbjoueuractif']>3) {
								$_SESSION['valeureenchere'] = '3-'.$vd;
							}
							//SINON S'il y a plus de 6 dés et plus de 2 joueurs actifs
							elseif ($_SESSION['nbdestotal']>4 AND $_SESSION['nbjoueuractif']>2) {
								$_SESSION['valeureenchere'] = '2-'.$vd;
							}
							//Sinon il enchère à 1 dé
							else {
								$_SESSION['valeureenchere'] = '1-'.$vd;
							}
							$_SESSION['tourpalifico']=true;
							bet_record ($_SESSION['numerojoueur'], $_SESSION['valeureenchere']);
						}	
						//Sinon si c'est la PREMIERE ENCHERE du TOUR mais que le BOT n'est PAS PALIFICO
						else {
							/*Tableau de fréquence de chaque valeur*/
							for ($i=1;$i<=6;$i++){
								$nbdebot[$i]=comptage_des_bot ($i,$_SESSION,$numerojoueur);
				//ia				$deroule .= $nbdebot[$i].'<br>';
							}
							//Initialisation des variables
							$nd=0;
							$vd=0;
							$joker=true;
							$ndtotal = $_SESSION['nbdestotal'] - $_SESSION['joueur'.$_SESSION['numerojoueur']]['nb_dice'];
							//Calcul des proba pour les 20 encheres suivantes (incrément de la valeur)
							for ($i=1;$i<=20;$i++){
								$enchere_suivante = enchere_suivante ($nd,$vd);
								$nd=$enchere_suivante[0];
								$vd=$enchere_suivante[1];
								$tbl_enchere_triee['nd'][$i]=$nd;
								$tbl_enchere_triee['vd'][$i]=$vd;
								$nbdedes_bot_enchere = $nd - $nbdebot[$vd] - $nbdebot[1];//Le bot prends en compte ses dés et ses pécos dans le calcul de la proba du nombre de dés demandés
								$tbl_enchere[$i]=proba_annonce ($ndtotal,$nbdedes_bot_enchere,$joker,$i,$nd,$vd);
				//ia				$deroule .= '<br> proba des annonces suivantes ('.$nd.'-'.$vd.') '.$tbl_enchere[$i];
							}
							//Calcul des proba pour les 6 encheres suivantes en pécos (incrément du nb de dé)
							$nd=1;
							for ($j=$i;$j<=$i+6;$j++){
								$vd=1;
								$joker=false;
								$tbl_enchere_triee['nd'][$j]=$nd;
								$tbl_enchere_triee['vd'][$j]=$vd;
								$nbdedes_bot_enchere = $nd - $nbdebot[1];//Le bot prends en compte le nb de pécos qu'il possède pour déterminer la proba du nombre de dés demandés
								$tbl_enchere[$j]=proba_annonce ($ndtotal,$nbdedes_bot_enchere,$joker,$j,$nd,$vd);
								
				//ia				$deroule .= '<br>proba des annonces suivantes ('.$nd.'-'.$vd.') '.$tbl_enchere[$j];
								$nd=$nd+1;
							}
							//Trie des proba dans un tableau
							$i=1;
							arsort ($tbl_enchere);
							foreach ($tbl_enchere as $key => $val) {
				//ia				$deroule .= '<br>'.$key.' = '.$val;
								$tbl_enchere_triee['numero'][$i]=$key;
								$tbl_enchere_triee['proba'][$i]=$val;
								$i++;
							}
							$i=1;
							///////////////////////////////////////////////////////
							for ($i=1;$tbl_enchere_triee['proba'][$i]>=0.75;$i++) {
							
							}
				//ia			$deroule .= 'i vaut '.$i;
							$j=1;
							for ($j=$i;$tbl_enchere_triee['proba'][$j]>=0.5;$j++) {

							}
				//ia			$deroule .= 'j vaut '.$j;
							$k=1;
							for ($k=$i;$tbl_enchere_triee['proba'][$k]>=0.3;$k++) {
							}
				//ia			$deroule .= 'k vaut '.$k;
							/*TODO : Chaque enchère dont la proba est > 0 est une candidate à l'enchère choisie
							Parmi les proba p, on prends seulement celles > 0.75 s'il y en a.
							Sinon on prends 0.5< p <=0.75
							Sinon on prends 0.3< p <=0.5
							Sinon si la proba de l'enchère précédente est < aux enchères disponible => DUDO
							
							*/
				//ia			$deroule .= '<br>Meilleure enchere ='.$tbl_enchere_triee['proba'][1];
							//Dés correspondant à cette enchère :
				//ia			$deroule .= '<br>Enchere ='.$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
							//TODO : simplifier : enregistrer directement dans la session $_SESSION[joueur][bet value][0].
							$valeureenchere=$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
							bet_record ($numerojoueur, $valeureenchere);
						}
						/*A Qui est-ce de jouer ?
						Le bot vient de jouer, c'est au joueur suivant*/
						$_SESSION['rejoue']=false;
						$_SESSION['ancienjoueur']=$_SESSION['numerojoueur'];
						$tbl_a_qui_de_jouer=a_qui_de_jouer ($_SESSION['numerojoueur'], $_SESSION['rejoue']);
						$_SESSION['numerojoueur']=$tbl_a_qui_de_jouer[0];
						$_SESSION['rejoue']=$tbl_a_qui_de_jouer[1];
					}
					//SINON Si ce n'est PAS la première enchère-------------------------------------------
					else {
						//initialisation des variables de la dernière enchère
						$nd = $_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0];
						$vd = $_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1];
						$joker=true;
						$ndtotal = $_SESSION['nbdestotal'] - $_SESSION['joueur'.$_SESSION['numerojoueur']]['nb_dice'];
						$nbdedes_enchere_precedente=0;
						/*On range tous les dés du BOT dans un tableau indiquant la fréquence de chaque valeur*/
						for ($i=1;$i<=6;$i++){
							$nbdebot[$i]=comptage_des_bot ($i,$_SESSION,$_SESSION['numerojoueur']);
							//Si le bot a des dés de la valeur de l'enchere précédente, il prends en compte ces dés pour déterminer la proba de l'enchère précédente. 
							if ($vd==$i) {
								$nbdedes_enchere_precedente = $nd - $nbdebot[$i];
							}
						}
						//Si on est dans un tour avec un joueur palifico----------------------------------
						if ($_SESSION['tourpalifico']==true) {	
							$_SESSION['joueurpalifico']=true;
							$joker=false;	
							$proba_joueur_precedent[0]= proba_annonce ($ndtotal,$nbdedes_enchere_precedente,$joker,0,$nd,$vd);
				//ia			$deroule .= '<br>tata proba enchere precedente : '.$proba_joueur_precedent[0].'<br>';
							//Si l'enchere precedente a une proba de 0 alors le bot dit "Dudo".
							if ($proba_joueur_precedent[0]==0) {
								$_SESSION['enchere']='Dudo';
								$_SESSION['joueurencours']=$_SESSION['numerojoueur'];
							}
							//Sinon le bot détermine une enchère valable
							else {
								for ($i=1;$nd<=$_SESSION['nbdestotal']-1;$i++){
									$nd=$nd+1;
									$tbl_enchere_triee['nd'][$i]=$nd;
									$tbl_enchere_triee['vd'][$i]=$vd;
									$tbl_enchere[$i]=proba_annonce ($ndtotal,$nbdedes_enchere_precedente,$joker,$i,$nd,$vd);
				//ia					$deroule .= '<br> proba des annonces suivantes ('.$nd.'-'.$vd.') '.$tbl_enchere[$i];
									//proba '.$tbl_enchere['proba'.$i][0].'nb_de '.$tbl_enchere['proba'.$i][1].'val_de '.$tbl_enchere['proba'.$i][2];
								}
								//On range les proba de la plus grande à la plus petite valeur
								$i=1;
								arsort ($tbl_enchere);
								foreach ($tbl_enchere as $key => $val) {
				//ia					$deroule .= '<br>'.$key.' = '.$val;
									$tbl_enchere_triee['numero'][$i]=$key;
									$tbl_enchere_triee['proba'][$i]=$val;
									$i++;
								}
								//Dés correspondant à cette enchère :
				//ia				$deroule .= '<br>Enchere ='.$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
								$valeureenchere=$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
								bet_record ($_SESSION['numerojoueur'], $_SESSION['valeureenchere']);
							}
						}
						//Sinon c'est une enchère normale en cours de tour et sans joueur palifico
						else {
							//Evalue la proba de l'enchère précédente
							//Si l'enchère précédente est en pécos
							if ($vd==1){$joker=false;}
							//Sinon on compte les pécos comme joker pour déterminer le nb de dés de l'enchère
							else {$nbdedes_enchere_precedente= $nbdedes_enchere_precedente - $nbdebot[1];}
							$proba_joueur_precedent[0]= proba_annonce ($ndtotal,$nbdedes_enchere_precedente,$joker,0,$nd,$vd);
				//ia			$deroule .= '<br>tata proba enchere precedente : '.$proba_joueur_precedent[0].'<br>';
							//Si l'enchere precedente a une proba de 0 alors le bot dit "Dudo".
							if ($proba_joueur_precedent[0]==0) {
								$_SESSION['enchere']='Dudo';
								$_SESSION['joueurencours']=$_SESSION['numerojoueur'];
							}
							//Sinon détermine les proba pour les 20 encheres suivantes (incrément de la valeur)
							else {
								for ($i=1;$i<=20;$i++){
									$enchere_suivante = enchere_suivante ($nd,$vd);
									$nd=$enchere_suivante[0];
									$vd=$enchere_suivante[1];
									$nbdedes_bot_enchere = $nd - $nbdebot[$vd];//Le bot ne prends en compte ses dés en MOINS dans le nombre de dés demandés s'il en possède de cette valeur
									$tbl_enchere_triee['nd'][$i]=$nd;
									$tbl_enchere_triee['vd'][$i]=$vd;
									//Si l'enchere est en pécos on ne les compte pas comme joker <- inutile
									if ($vd==1){
										$joker=false;
									}
									$tbl_enchere[$i]=proba_annonce ($ndtotal,$nbdedes_bot_enchere,$joker,$i,$nd,$vd);
				//ia					$deroule .= '<br> proba des annonces suivantes ('.$nd.'-'.$vd.') '.$tbl_enchere[$i];
									//proba '.$tbl_enchere['proba'.$i][0].'nb_de '.$tbl_enchere['proba'.$i][1].'val_de '.$tbl_enchere['proba'.$i][2];
								}
								/*Calcul des proba pour les 5 enchères suivantes en PECOS
								Si la valeur différente de pécos*/
								if ($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1]==1) {	
									$nd=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]+1;		
								}
								//Sinon Si le nombre de dés annoncé est paire
								elseif (!($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]&1)) {		
									$nd=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]/2;		
								}
								else {
									$nd=($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]+1)/2;
								}
								for ($j=$i;$j<=$i+5;$j++){
									$vd=1;
									$joker=false;
									$nbdedes_bot_enchere = $nd - $nbdebot[1];//Le bot ne prends en compte ses dés en MOINS dans le nombre de dés demandés s'il en possède de cette valeur
									$tbl_enchere[$j]=proba_annonce ($ndtotal,$nbdedes_bot_enchere,$joker,$j,$nd,$vd);
									$tbl_enchere_triee['nd'][$j]=$nd;
									$tbl_enchere_triee['vd'][$j]=$vd;
				//ia					$deroule .= '<br>proba des annonces suivantes ('.$nd.'-'.$vd.') '.$tbl_enchere[$j];
									$nd=$nd+1;
								}
								//Trie des proba dans un tableau--------------------------------------
								$i=1;
								arsort ($tbl_enchere);
								foreach ($tbl_enchere as $key => $val) {
				//ia					$deroule .= '<br>'.$key.' = '.$val;
									$tbl_enchere_triee['numero'][$i]=$key;
									$tbl_enchere_triee['proba'][$i]=$val;
									$i++;
								}
				//ia				$deroule .= '<br>Meilleure enchere ='.$tbl_enchere_triee['proba'][1];
								//Dés correspondant à cette enchère :
				//ia				$deroule .= '<br>Enchere ='.$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
								$_SESSION['valeureenchere']=$tbl_enchere_triee['nd'][$tbl_enchere_triee['numero'][1]].'-'.$tbl_enchere_triee['vd'][$tbl_enchere_triee['numero'][1]];
								bet_record ($_SESSION['numerojoueur'], $_SESSION['valeureenchere']);
							}	
						}	
					}
					/*--------------------------A Qui est-ce de jouer ? ------------------------------------------
					Si un joueur vient de jouer, c'est au suivant*/
					if (isset ($_SESSION['ancienjoueur'])){
						if (!isset($_SESSION['rejoue'])) {
							$_SESSION['rejoue']=false;
						}
						//Si le bot n'a pas fait d'enchère spéciale (dudo ou calza) alors il devient l'ancien joueur. 
						if ($_SESSION['enchere']!='Dudo' OR $_SESSION['enchere']!='Calza') {
							$_SESSION['ancienjoueur']=$_SESSION['numerojoueur'];
						}
						$tbl_a_qui_de_jouer=a_qui_de_jouer ($_SESSION['numerojoueur'], $_SESSION['rejoue']);
						$_SESSION['numerojoueur']=$tbl_a_qui_de_jouer[0];
						$_SESSION['rejoue']=$tbl_a_qui_de_jouer[1];
					}
					//???? TODO test !!
					if (!isset($_SESSION['joueurpalifico'])){$_SESSION['joueurpalifico']=false;}
					if (!isset($_SESSION['rejoue'])){$_SESSION['rejoue']=false;}
				}
//-------------------------------------L'humain joue son tour -------------------------------------------- 
				//Si un joueur vient d'entrer son nom ou Si une enchere autre que DUDO ou CALZA a été donnée ou Si c'est un nouveau tour
				if ((isset($_SESSION['formulaire_joueur_appelle']) AND $_SESSION['formulaire_joueur_appelle']==true) OR (isset($_SESSION['enchere']) AND $_SESSION['enchere']!='Calza' AND $_SESSION['enchere']!='Dudo') OR isset($_POST['nouveautour'])) {
					//Affiche le nb de joueurs
					unset($_SESSION['formulaire_joueur_appelle']);
					
					$game_global_inf = new game_global_inf;
					$game_global_inf->totalplayer = $_SESSION['nbjoueuractif'];
					$game_global_inf->totaldices = $_SESSION['nbdestotal'];
						
					$tplgameglobalinf = new Hyla_Tpl('tools/perudo/presentation/templates');
					$tplgameglobalinf->importFile('tplgameglobalinf', 'game_global_inf.tpl.html');
					$tplgameglobalinf->setVar('lang', $lang);
					$tplgameglobalinf->setVar('game_global_inf', $game_global_inf);
					
					$deroule .= $tplgameglobalinf->render();
	
					/*Si une enchère a été faite on l'affiche, 
					ainsi que toutes les précédentes  faites dans ce tour.*/
					if (isset($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0])) {
						for ($n=1;$n<=$_SESSION['nbjoueur'];$n++) {
							if (isset($_SESSION['joueur'.$n]['bet_value'][0])) {
								
								$previous_turn = new previous_turn;
								$previous_turn->playername = $_SESSION['joueur'.$n]['nom'];
								$previous_turn->playerbidnb = $_SESSION['joueur'.$n]['bet_value'][0];
								//TODO : supprimer et vérifier stabilité int $nd
								$_SESSION['joueur'.$n]['bet_value'][1]=intval ($_SESSION['joueur'.$n]['bet_value'][1]);
								$previous_turn->playerbidvalue = $_SESSION['joueur'.$n]['bet_value'][1];
								$previous_turn->playerdicenb = $_SESSION['joueur'.$n]['nb_dice'];			
									
								$tplpreviousturn = new Hyla_Tpl('tools/perudo/presentation/templates');
								$tplpreviousturn->importFile('tplpreviousturn', 'previous_turn.tpl.html');
								$tplpreviousturn->setVar('lang', $lang);
								$tplpreviousturn->setVar('previous_turn', $previous_turn);
								
								$deroule .= $tplpreviousturn->render();
							}
						}
						//Formulaire des enchères DUDO et CALZA affiché à partir du moment où une première enchère a été faite
						$dudo_or_calza = new form_dudo_or_calza;
						$dudo_or_calza->ancienjoueur = $_SESSION['ancienjoueur'];
						$dudo_or_calza->joueurencours = $_SESSION['numerojoueur'];

						$tpldudocalza = new Hyla_Tpl('tools/perudo/presentation/templates');
						$tpldudocalza->importFile('tpldudocalza', 'dudo_or_calza_form.tpl.html');
						$tpldudocalza->setVar('dudo_or_calza', $dudo_or_calza);
						$tpldudocalza->setVar('formlink', $formlink);
						
						$deroule .= $tpldudocalza->render();
					}
					$current_turn = new current_turn;
					$current_turn->playername = $_SESSION['joueur'.$_SESSION['numerojoueur']]['nom'];
						
					$tplcurrentturn = new Hyla_Tpl('tools/perudo/presentation/templates');
					$tplcurrentturn->importFile('tplcurrentturn', 'current_turn.tpl.html');
					$tplcurrentturn->setVar('lang', $lang);
					$tplcurrentturn->setVar('current_turn', $current_turn);
					
					$deroule .= $tplcurrentturn->render();
					
					/* Donne les valeurs des dés du joueur en train de jouer*/				
					$tplplayerdice = new Hyla_Tpl('tools/perudo/presentation/templates');
					$tplplayerdice->importFile('dice_img', 'dice_img.tpl.html');

					// Données
					$tbl_dice_value = $_SESSION['joueur'.$_SESSION['numerojoueur']]['dice_value'];
					$tplplayerdice->setVar('dice_count', count($tbl_dice_value));
					// Parcours des données
					foreach ($tbl_dice_value as $value) {
						$tplplayerdice->setVar('value', $value);
						$tplplayerdice->render('line');
					}
					// Affiche la liste sous forme de logos de dés
					$deroule .= $tplplayerdice->render();
					
					/* Si on est dans un tour PALIFICO--------------------------------------------------
					alors la valeur du dé est fixée par le joueur palifico.*/
					if (isset($_SESSION['tourpalifico']) AND $_SESSION['tourpalifico']!=''){
						/* & si on connait la dernière enchère,
						la valeur du nombre de dés est incrémenté d'un point.*/
						if (isset($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0])) {
							$nd=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]+1;
							$vd=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1];
						}
						/* Formulaire des enchères PALIFICO*/
						//Si l'enchère n'est pas maximale on affiche la possibilité d'enchérir.
						if ($nd<=$_SESSION['nbdestotal']) {
		
							$bid_form = new bid_form;
							$bid_form->numerojoueur = $_SESSION['numerojoueur'];
							$bid_form->tourpalifico = $_SESSION['tourpalifico'];
							
							// Donne la liste des enchères possibles							
							$tplbidform = new Hyla_Tpl('tools/perudo/presentation/templates');
							$tplbidform->importFile('bid_form', 'bid_form.tpl.html');
							$tplbidform->setVar('lang', $lang);
							$tplbidform->setVar('bid_form', $bid_form);
							
							// Parcours les données

							for ($i=$nd;$i<=$_SESSION['nbdestotal'];$i++) {
								$tbl_dice_bid[] = array ('nb'=> $i, 'val'=> $vd);
							}
							$tplbidform->setVar('bid_count', count($tbl_dice_bid));
							foreach ($tbl_dice_bid as $enchere) {
									$tplbidform->setVar('enchere', $enchere);
									$tplbidform->render('line');
							}
							// Affiche la liste en intégrant des logos de dés
							$deroule .= $tplbidform->render();
														
							//Le prochain joueur aura aussi un choix palifico
							$_SESSION['joueurpalifico']=true;
						}
					}
					//Sinon on n'est PAS dans un tour palifico
					else {
						/*Si on connait la dernière enchère,
						la valeur englobée de la prochaine enchère minimale est incrémenté d'un point.*/
						if (isset($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0])) {
							$enchereminimalpecos=0;
							$enchere_suivante = enchere_suivante ($_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0],$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1]);
							$nd=$enchere_suivante[0];
							$vd=$enchere_suivante[1];
							$enchereminimalpecos=$enchere_suivante[2];						
						}
						//Sinon on part de l'enchère minimale.
						else {
							$nd=1;
							$vd=2;
							$enchereminimalpecos=0;
						}
						//Liste des enchères possibles
												
						$bid_form = new bid_form;
						$bid_form->numerojoueur = $_SESSION['numerojoueur'];
						$bid_form->tourpalifico = $_SESSION['joueurpalifico'];
						
						// Donne la liste des enchères possibles							
						$tplbidform = new Hyla_Tpl('tools/perudo/presentation/templates');
						$tplbidform->importFile('bid_form', 'bid_form.tpl.html');
						$tplbidform->setVar('lang', $lang);
						$tplbidform->setVar('bid_form', $bid_form);
						$tplbidform->setVar('formlink', $formlink);
						
						// Parcours les données
						for ($i=$nd; $i<=$_SESSION['nbdestotal']; $i++) { 
							for ($j=$vd;$j<=6;$j++) {
								$tbl_dice_bid[] = array ('nb'=> $i, 'val'=> $j);
								/*Si le nombre de dés annoncé est paire et la valeur égale à 6, 
								on affiche l'enchère en pécos
								TODO : afficher le bon choix au niveau des pécos*/
								if (!($i&1) and $j==6) {
									$p=$i/2;
									$tbl_dice_bid[] = array ('nb'=> $p, 'val'=> 1);
								}
							}
							$vd=2;
						}
						for ($i=$enchereminimalpecos;$i<=$_SESSION['nbdestotal'];$i++) {
							$tbl_dice_bid[] = array ('nb'=> $i, 'val'=> 1);
						}
						$tplbidform->setVar('bid_count', count($tbl_dice_bid));
						foreach ($tbl_dice_bid as $enchere) {
								$tplbidform->setVar('enchere', $enchere);
								$tplbidform->render('line');
						}
						// Affiche la liste en intégrant des logos de dés
						$deroule .= $tplbidform->render();	
					}
				}
//-------------------Fin du tour de jeu : il y a un DUDO ou un CALZA--------------------------------------
				//Si l'enchère est calza ou dudo mais pas une enchère standard
				if (isset($_SESSION['enchere']) AND (($_SESSION['enchere']=='Calza' OR $_SESSION['enchere']=='Dudo'))) {

					$bid_resume_end_turn = new bid_resume_end_turn;
					$bid_resume_end_turn->lastplayername = $_SESSION['joueur'.$_SESSION['ancienjoueur']]['nom'];
					$bid_resume_end_turn->lastplayerbidnb = $_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0];
					$bid_resume_end_turn->lastplayerbidvalue = $_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1];
					$bid_resume_end_turn->playername = $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'];			
					$bid_resume_end_turn->enchere = $_SESSION['enchere'];			

					$tplbidresumeendturn = new Hyla_Tpl('tools/perudo/presentation/templates');
					$tplbidresumeendturn->importFile('tplbidresumeendturn', 'bid_resume_end_turn.tpl.html');
							
					$tplbidresumeendturn->setVar('lang', $lang);
					$tplbidresumeendturn->setVar('bid_resume_end_turn', $bid_resume_end_turn);
					
					// Affiche le résumé des deux dernières annonces (enchère + Dudo ou Calza)		
					$deroule .= $tplbidresumeendturn->render();
					
					//Initialisation des variables
					//TODO : à placer plus bas !
					$valeurdude=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][1];
					$nbdedes=comptage_des($valeurdude, $_SESSION);

					//Affiche tous les dés de tous les joueurs qui ont encore des dés.
					for ($n=1;$n<=$_SESSION['nbjoueur'];$n++) {
						if($_SESSION['joueur'.$n]['nb_dice']!=0) {
							//Si un joueur n'a pas de NOM, son nom temporaire est le nom joueur$n.
							if (!isset($_SESSION['joueur'.$n]['nom'])){
								$nomdujoueur = 'Joueur'.$n;
								//$deroule .= $nomdujoueur.' : '.implode (', ', $_SESSION['joueur'.$n]['dice_value']).'<br>';
							}
							//Sinon son NOM est récupéré dans la session.
							else {	
								$nomdujoueur = $_SESSION['joueur'.$n]['nom'];		
							}	
							$each_playr_bid_end_turn = new each_playr_bid_end_turn;
							$each_playr_bid_end_turn->nomdujoueur = $nomdujoueur;

							$tpleachplayrbidendturn = new Hyla_Tpl('tools/perudo/presentation/templates');
							$tpleachplayrbidendturn->importFile('tpleachplayrbidendturn', 'each_playr_bid_end_turn.tpl.html');
							
							// Données
							$tbl_dices[$n] = $_SESSION['joueur'.$n]['dice_value'];
							// Parcours des données
							foreach ($tbl_dices[$n] as $value) {
								$tpleachplayrbidendturn->setVar('value', $value);
								$tpleachplayrbidendturn->render('line');
							}
						}	
						$tpleachplayrbidendturn->setVar('each_playr_bid_end_turn', $each_playr_bid_end_turn);
						// Affiche la liste des dés de chaque joueur
						$deroule .= $tpleachplayrbidendturn->render();
					}

					/*Calcul du nombre de dés correspondant à l'enchère
					Si la valeur annoncé N'est PAS pécos et Si on n'est pas dans un tour PALIFICO, 
					on ajoute les pécos au nb de dé total.*/
					if ($valeurdude!=1 AND (isset($_SESSION['tourpalifico']) OR $_SESSION['joueurpalifico']==false)) {
						$nbpecos=comptage_des(1, $_SESSION);
						$nbdedes =$nbpecos+$nbdedes;
						//S'il y a des pécos on en affiche le nb ainsi que celui des dés affichant la valeur annoncée.
						if ($nbpecos!=0) {
							$deroule .= ''.$nbpecos.' p&eacute;cos donc '.$nbdedes.' d&eacute;s '.$valeurdude.' en tout !<br>';
						}
					}
					//Sinon on affiche uniquement le nombre de Pécos.
					else {
						$deroule .= 'Il y a '.$nbdedes.' p&eacute;cos !<br>';
					}
					//Si l'enchère est DUDO
					if ($_SESSION['enchere']=='Dudo') {
						//S'il y a plus de dés qu'annoncés le joueur ayant dit dudo PERD un dé.
						if ($nbdedes>=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]) {
							$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' perd un d&eacute; pour dudo foireux';
							$_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']=$_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']-1;
							$_SESSION['rejoue']=false;
							$_SESSION['joueurpalifico']=false;
							//Si ce joueur n'a PLUS de dés, il y a un joueur actif en MOINS
							if ($_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']==0){
								$_SESSION['nbjoueuractif'] = $_SESSION['nbjoueuractif']-1;
								$_SESSION['joueurpalifico']=false;
							}
							//Sinon si ce joueur n'as plus qu'UN SEUL dé, il devient Palifico.
							elseif ($_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']==1){
								$_SESSION['joueurpalifico']=true;
								$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' est PALIFICO !!';
							}
						}
						//Sinon c'est le joueur ayant fait l'annonce qui PERD un dé et qui REJOUE.
						else {
							$deroule .= $_SESSION['joueur'.$_SESSION['ancienjoueur']]['nom'].' perd un d&eacute; pour annonce foireuse';
							$_SESSION['joueur'.$_SESSION['ancienjoueur']]['nb_dice']=$_SESSION['joueur'.$_SESSION['ancienjoueur']]['nb_dice']-1;
							$_SESSION['rejoue']=true;
							$_SESSION['joueurpalifico']=false;
							//Si ce joueur n'a PLUS de dés, il y a un joueur actif en MOINS
							if ($_SESSION['joueur'.$_SESSION['ancienjoueur']]['nb_dice']==0){
								$_SESSION['nbjoueuractif'] = $_SESSION['nbjoueuractif']-1;
								$_SESSION['joueurpalifico']=false;
							}
							//Sinon si ce joueur n'as plus qu'UN SEUL dé, il devient Palifico.
							elseif ($_SESSION['joueur'.$_SESSION['ancienjoueur']]['nb_dice']==1){
								$_SESSION['joueurpalifico']=true;
								$deroule .= '<br>'.$_SESSION['joueur'.$_SESSION['ancienjoueur']]['nom'].' est PALIFICO !!';
							}
						}
						//Les joueurs ramassent leurs dés : les valeurs sont effacés.
						for ($i=1;$i<=6;$i++) {
							for ($j=0;$j<=5;$j++){
								unset($_SESSION['joueur'.$i]['dice_value'][$j]);
								unset($_SESSION['joueur'.$i]['bet_value'][$j]);
							}
							unset($_SESSION['tourpalifico']);
							unset($_SESSION['enchere']);
							
						}
						//Il y a eu un dudo : il y a donc un dé de MOINS au total.
						$_SESSION['nbdestotal']=$_SESSION['nbdestotal']-1;
						//Formulaire du nouveau tour
						$deroule .= '<form method="post" action="'.$this->href().'">
							<input type="hidden" value="'.$_SESSION['ancienjoueur'].'"  name="ancienjoueur"/>
							<input type="hidden" value="'.$_SESSION['numerojoueur'].'"  name="joueurencours"/>
							<input type="hidden" value="'.$_SESSION['rejoue'].'"  name="rejoue"/>
							<input type="hidden" value="'.$_SESSION['joueurpalifico'].'"  name="joueurpalifico"/>
							<input type="submit" value="Tour suivant" name="nouveautour"/>
							</form>';
					}
					//Sinon l'enchère est calza
					else {
						/*S'il y a exactement autant de dés qu'annoncés, 
						et que le joueur ayant dit "Calza" a moins de 5 dés, il en GAGNE 1 puis joue.
						*/
						if ($nbdedes==$_SESSION['joueur'.$_SESSION['ancienjoueur']]['bet_value'][0]) {
							if ($_SESSION['joueur'.$_POST['joueurencours']]['nb_dice']<5 ) {
								$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' a trouv&eacute; un Calza !! Il gagne un d&eacute';
								$_SESSION['joueur'.$_POST['joueurencours']]['nb_dice']=$_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']+1;
								$_SESSION['rejoue']=false;
								$_SESSION['joueurpalifico']=false;
								$_SESSION['nbdestotal']=$_SESSION['nbdestotal']+1;
							}
							else {
								$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' ne gagne aucun d&eacute car il en a d&eacute;j&agrave 5';
								$_SESSION['rejoue']=false;
								$_SESSION['joueurpalifico']=false;
							}
						}
						//Sinon le joueur ayant annoncé "Calza" PERD un dé. 
						else {
							$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' perd un d&eacute; pour calza foireux';
							$_SESSION['joueur'.$_POST['joueurencours']]['nb_dice']=$_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']-1;
							$_SESSION['rejoue']=false;
							$_SESSION['joueurpalifico']=false;
							//Si un joueur n'a plus de dés, il n'est plus compté parmi les joueurs actif.
							if ($_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']==0){
								$_SESSION['nbjoueuractif'] = $_SESSION['nbjoueuractif']-1;
								$_SESSION['joueurpalifico']=false;
							}
							//Sinon si ce joueur n'as plus qu'UN SEUL dé, il devient Palifico.
							if ($_SESSION['joueur'.$_SESSION['joueurencours']]['nb_dice']==1){
								$_SESSION['joueurpalifico']=true;
								$deroule .= $_SESSION['joueur'.$_SESSION['joueurencours']]['nom'].' est PALIFICO !!';
							}
							//On décrémente le nombre total de dé.
							$_SESSION['nbdestotal']=$_SESSION['nbdestotal']-1;
						}
						//Les joueurs ramassent leurs dés : les valeurs de la session sont effacés.
						for ($i=1;$i<=6;$i++) {
							for ($j=0;$j<=5;$j++){
								unset($_SESSION['joueur'.$i]['dice_value'][$j]);
								unset($_SESSION['joueur'.$i]['bet_value'][$j]);
							}
							unset($_SESSION['tourpalifico']);
							unset($_SESSION['enchere']);

						}
						//Formulaire nouveau tour.
						$deroule .= '<form method="post" action="'.$this->href().'">
						<input type="hidden" value="'.$_SESSION['ancienjoueur'].'"  name="ancienjoueur"/>
						<input type="hidden" value="'.$_SESSION['numerojoueur'].'"  name="joueurencours"/>
						<input type="hidden" value="'.$_SESSION['rejoue'].'"  name="rejoue"/>
						<input type="hidden" value="'.$_SESSION['joueurpalifico'].'"  name="joueurpalifico"/>
						<input type="submit" value="Tour suivant" name="nouveautour"/>
						</form>'; 
					}
				}
			}	
		}
	//Formulaire nouvelle partie / destruction de session
	$form_new_game = '<form name="Nouvelle Partie" method="post" action="'.$this->href().'">
			<br><input type="submit" name="newgame" value="Quitter la partie"/>
			</form>';
	}
	
			
			//TEMPLATE GENERAL DE L'AFFICHAGE
		
	// Créé l'objet Hyla_Tpl
	$tpl = new Hyla_Tpl('tools/perudo/presentation/templates');
	$tpl->importFile('index', 'index.tpl.html');

	
	//interface générale, avec les bons affichages dans déroulé et interaction en fonction de l'endroit ou on est de la partie
	class interface_perudo {
		var $deroule;
		var $interaction;
		var $form_new_game;
	}
	$output = new interface_perudo;
	$output->deroule = $deroule;
	$output->interaction = $interaction;
	$output->form_new_game = $form_new_game;

	$tpl->setVar('lang', $lang);
	$tpl->setVar('output', $output);
	
	// Affiche le résultat de l'interface de perudo a l'écran
	echo $tpl->render();
			
 //var_dump ($_SESSION);
 //var_dump ($_POST);
	?>
