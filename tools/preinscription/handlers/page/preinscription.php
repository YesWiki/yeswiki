<?php
//vérification de sécurité
if (!eregi("wakka.php", $_SERVER['PHP_SELF'])) {
    die ("acc&egrave;s direct interdit");
}

?>
<div class="page">
<?php
if (isset($_POST['Expediteur'])) 
{
	if($_POST["jsenabled"] != 'Y') 
	{
		$msg = "Votre inscription n'a pas &eacute;t&eacute; enregistr&eacute;e car ce wiki pense que vous etes un robot. Activez javascript et actualisez la page.";
		$_POST["Expediteur"] = '';	
	}
	else 
	{
		set_include_path('tools/preinscription/libs/'.PATH_SEPARATOR.get_include_path());
		include_once 'tools/preinscription/libs/Mail.php' ;
		include_once 'tools/preinscription/libs/Mail/mime.php' ;
	    $result = $this->LoadSingle("SELECT COUNT(*) as count FROM ".$this->config["table_prefix"]."triples WHERE ".
	                "resource = '".mysql_escape_string($this->tag)."' AND ".
	                "property  = '".mysql_escape_string('http://outils-reseaux.org/_vocabulary/preinscription')."' AND ".
	                "value     LIKE '".$_POST['Expediteur']."%'");
	                
	    if ($result['count'] >= 1) {
	                $msg = "Vous êtes déja inscrit à cette formation.";
	    }
	    else {
		        $this->Query("insert into ".$this->config["table_prefix"]."triples set ".
	            "resource = '".mysql_escape_string($this->tag)."', ".
	            "property  = '".mysql_escape_string('http://outils-reseaux.org/_vocabulary/preinscription')."', ".
	            "value     = '".mysql_escape_string(trim($_POST['Expediteur']).'|'.trim($_POST['Prenom']).'|'.trim($_POST['Nom']).'|'.trim($_POST['Tarif']))."' ");
	            
				$email = trim($_POST['Expediteur']) ;

				$texte_mail = 'Bonjour, nous confirmons votre pré-inscription à la formation décrite sur cette page '.$this->href().'.'."\n\n";
				$texte_mail .= 'Une jauge présente sur cette page vous indique la progression des inscriptions au fur et à mesure.'."\n";
				$texte_mail .= 'Une fois le quota de pré-inscrits acquis, une date sera proposée aux stagiaires.'."\n\n";
				$texte_mail .= "Prénom : ".$_POST['Prenom']."\n";					
				$texte_mail .= "Nom : ".$_POST['Nom']."\n";
				$texte_mail .= "Adresse mail : ".$_POST['Expediteur']."\n";	
				$texte_mail .= "Tarif : ".$_POST['Tarif']." euros\n\n";			
				$texte_mail .= 'Vous pouvez consulter les formations auxquelles vous vous êtes inscrit et vous désinscrire à la page http://outils-reseaux.org/wakka.php?wiki=InscriptionFormation&email='.$email.' '."\n\n";
				$texte_mail .= "\n\n".'Coopérativement votre !'."\n";
				$texte_mail .= 'L\'équipe Outils-Réseaux'."\n";
				$texte_mail .= 'http://outils-reseaux.org'."\n";
				
				$html_mail = '<html>
				<head>
				<title>Webzine Outils-Réseaux</title>
				<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
				<style type="text/css">
				<!--
				body,td,th{font-family:Verdana, Arial, sans-serif;font-size:11px;text-align:justify;}
				body{background-color:#FFF;text-align:center;margin:0;}
				#paragraphe-gauche,#paragraphe-droite{text-align:justify;width:350px;vertical-align:top;line-height:20px;font-size:12px;padding:20px;}
				#conteneur_cyberlettre h1{background:url(http://outils-reseaux.org/communication/Webzine/Puce-h3.gif) no-repeat top left;font-size:15px;color:#9ED13B;margin:0;padding:20px 0 0 40px;}
				#conteneur_cyberlettre h2{width:100%;color:#0dafff;border-bottom:2px solid #EEE;font-size:14px;margin:0 0 10px;padding:0;}
				#conteneur_cyberlettre h3{color:#0dafff;font-size:13px;margin:12px 0 6px;padding:0;}
				#conteneur_cyberlettre h4{color:#000;font-size:12px;font-weight:700;margin:0 0 6px;padding:0;}
				#conteneur_cyberlettre h5{width:100%;text-align:right;font-size:11px;color:#0dafff;}
				#conteneur_cyberlettre a{color:#930;text-decoration:none;font-weight:700;}
				#conteneur_cyberlettre a:hover{color:#e89326;text-decoration:none;}
				#conteneur_cyberlettre ul{list-style-image:url(http://outils-reseaux.org/tools/templates/themes/outils-reseaux/images/puce_liste.png);margin:0;padding:5px 0 5px 20px;}
				#conteneur_cyberlettre li{margin-bottom:5px;}
				-->
				</style>
				</head>
				
				<body>
				<div id="conteneur_cyberlettre">								
				<table width="700" border="0" cellpadding="5" cellspacing="1" style="border:dotted 2px #000000; background:url(http://outils-reseaux.org/communication/Formations/bandeau_formations.png) no-repeat top right; margin:5px auto;">
				<tr>
				<td colspan="2">
				<a id="logo-newsletter" style="float:left; display:block; background:url(http://outils-reseaux.org/communication/Webzine/logoOR.png) no-repeat top left; width:101px; height:130px; margin:30px 0 40px 50px;" href="http://outils-reseaux.org" title="Aller sur le site Outils-Réseaux.org"></a>
				</td>
				</tr>
				<tr>
				<td colspan="2" style="padding:0 30px 30px 30px;">';
				
				$in=array('`((?:https?|ftp)://\S+)(\s|\z)`', '`((?<!//)(www\.)\S+)(\s|\z)`');
				$out=array('<a href="$1">$1</a>&nbsp;','&nbsp;<a href="http://$1">$1</a>&nbsp;');
				$html_contenu_mail = preg_replace($in,$out,$texte_mail);				 
				$html_mail .= nl2br($html_contenu_mail);
				$html_mail .= '</td>
				</tr>
				<tr>
				<td colspan="2" id="ours" style="padding:20px; border-top:solid 1px #000000; text-align:center;">
				<b>Association "Outils-Réseaux",</b> <br /> chez Tela Botanica,
				Institut de Botanique, 163 rue Auguste Broussonnet, 34090 Montpellier <br />
				
				Tél : 09 74 53 12 21 - N° Siret : 508 158 755 00019 - APE 9499Z <br />
				Association prestataire de formation enregistrée sous le numéro 91 34 06579 34<br />
				<a href="mailto:accueil@outils-reseaux.org" title="Nous contacter par courriel">Nous contacter par courriel</a><br />
				<a href="http://www.outils-reseaux.org" title="Aller sur la page d\'accueil d\'Outils-Réseaux">www.outils-reseaux.org</a><br />
				<a href="http://outils-reseaux.org/wakka.php?wiki=WebzinE" title="Voir les options d\'abonnement par mail">S\'abonner ou se désabonner</a>	
				</td>
				</tr>
				</table>
				<a href="#conteneur_cyberlettre" title="Retour en haut de page">&uarr; remonter en haut de la page</a>	
				</div>
				</body>
				</html>';
				
				$crlf = "\n";
				$hdrs = array(
				              'From'    => $_POST['mailadmin'],
				              'Subject' => '[Outils-Réseaux : pré-inscription] Formation à la carte : '.$this->tag
				              );
				
				$mime = new Mail_mime($crlf);
				
				$mime->setTXTBody($texte_mail);
				$mime->setHTMLBody($html_mail);
								
				$body = $mime->get();
				$hdrs = $mime->headers($hdrs);
				
				$mail =& Mail::factory('mail');
				$mail->send($_POST['mailadmin'].','.$email, $hdrs, $body);
				if (PEAR::isError ($mail)) 
				{
			    	$msg = 'Le mail de préinscription n\'est pas parti... Erreur serveur...' ;
				}
				else
				{					
					$msg = 'Votre pré-inscription nous a bien été envoyée. Vous recevrez par mail un message de confirmation de pré-inscription.';					
				}	            
	    }
	}	
} else 
{
	$msg="Formulaire de pré-inscription mal saisi...";
}

$this->SetMessage($msg);
echo $msg;
$this->Redirect($this->config['base_url'].$_POST['pagerenvoi']);
?>
</div>
