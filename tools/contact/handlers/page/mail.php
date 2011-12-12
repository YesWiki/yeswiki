<?php


if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

//inclusion de la bibliothèque de fonctions pour l'envoi des mails
include_once 'tools/contact/libs/contact.functions.php';

$output = '';

//si le handler est appelé en ajax, on traite l'envoi de mail et on répond en ajax
if (isset($_POST['type']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
	//initialisation de variables passées en POST
	$mail_sender = (isset($_POST['email'])) ? trim($_POST['email']) : false;
	$mail_receiver = (isset($_POST['mail'])) ? trim($_POST['mail']) : false;
	$name_sender = (isset($_POST['name'])) ? stripslashes($_POST['name']) : false;

	//dans le cas d'une page wiki envoyée, on formate le message en html et en txt
	if ($_POST['type']=='mail') {
		$subject = ((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false);
		$message_html = html_entity_decode($this->Format($this->page["body"]));
		$message_txt = strip_tags($message_html);
	}

	//pour un envoi de mail classique, le message en txt
	else {
		$subject = ((isset($_POST['entete'])) ? '['.trim($_POST['entete']).'] ': '').
				((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false).
				(($name_sender) ? ' de '.$name_sender : '');
		$message_html = '';
		$message_txt = (isset($_POST['message'])) ? stripslashes($_POST['message']) : '';
	}

	if ($_POST['type']=='contact' || $_POST['type']=='mail') {
		//on verifie si tous les parametres sont bons
		$error = check_parameters_mail($_POST['type'], $mail_sender, $name_sender, $mail_receiver, $subject, $message_txt);

		//Si pas d'erreur on envoie
		if(!$error) {
			echo send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html);
		}

		//on affiche l'erreur sinon
		else {
			echo '<div class="error_box">'.$error.'</div>';
		}
	}
	elseif ($_POST['type']=='abonne' || $_POST['type']=='desabonne') {
	//on verifie si tous les parametres sont bons
		$error = check_parameters_mail($_POST['type'], $mail_sender, $name_sender, $mail_receiver, $subject, $message_txt);

		//Si pas d'erreur on envoie
		if(!$error) {
			echo send_mail($mail_sender, $name_sender, $mail_receiver, 'newsletter '.$_POST['type'], 'newsletter '.$_POST['type'], '', $_POST['type']);
		}

		//on affiche l'erreur sinon
		else {
			echo '<div class="error_box">'.$error.'</div>';
		}
	}
}

//sinon on affiche le formulaire d'envoi de mail
else {
	//si on est identifié
	if ($this->GetUser()) {
		//on vérifie si l'on est bien identifié comme admin, pour éviter le spam
		if ($this->UserIsAdmin()) {
			$output .= '<div class="formulairemail">
			<h1>Envoyer la page par mail</h1>
			<div class="note"></div>
			<form id="ajax-mail-form" class="ajax-form" action="'.$this->href('mail').'">
				<label class="label-right">Votre adresse mail</label><input class="textbox" type="text" name="email" value="" /><br />
				<label class="label-right">Sujet du message</label><input class="textbox" type="text" name="subject" value="" /><br />
				<label class="label-right">Adresse mail du destinataire</label><input class="textbox" name="mail" value="" /><br />
				<label class="label-right">&nbsp;</label><input class="button" type="submit" name="submit" value="Envoyer" />
				<input type="hidden" name="type" value="mail" />
			</form>
			<div class="clear"></div>
			</div>
			';
		}
		//message d'erreur si pas admin
		else {
			$output .= '<div class="error_box">Le handler /mail est r&eacute;serv&eacute; au groupe des administrateurs.</div>';
		}
	}

	//on affiche le formulaire d'indentification sinon
	else {
		$output .= '<div class="info_box">Le handler /mail est r&eacute;serv&eacute; au groupe des administrateurs. Si vous faites parti ce groupe, veuillez vous identifier.</div>';
		$output .= $this->Format('{{login templateform="form_minimal.tpl.html"}}');
	}

	//affichage à l'écran
	echo $this->Header();
	echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
	echo $this->Footer();
}
?>
