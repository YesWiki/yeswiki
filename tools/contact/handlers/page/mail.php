<?php


if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

// inclusion de la bibliotheque de fonctions pour l'envoi des mails
include_once 'tools/contact/libs/contact.functions.php';

$output = '';

// si le handler est appele en ajax, on traite l'envoi de mail et on repond en ajax
if (isset($_POST['type']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest')) {
	//initialisation de variables passees en POST
	$mail_sender = (isset($_POST['email'])) ? trim($_POST['email']) : false;
	$mail_receiver = (isset($_POST['mail'])) ? trim($_POST['mail']) : (isset($_POST['nbactionmail'])) ? FindMailFromWikiPage($this->page["body"],$_POST['nbactionmail']) : false;
	$name_sender = (isset($_POST['name'])) ? stripslashes($_POST['name']) : false;

	// dans le cas d'une page wiki envoyee, on formate le message en html et en txt
	if ($_POST['type']=='mail') {
		$subject = ((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false);
		$message_html = html_entity_decode($this->Format($this->page["body"]));
		$message_txt = strip_tags($message_html);
	}

	// pour un envoi de mail classique, le message en txt
	else {
		$subject = ((isset($_POST['entete'])) ? '['.trim($_POST['entete']).'] ': '').
				((isset($_POST['subject'])) ? stripslashes($_POST['subject']) : false).
				(($name_sender) ? ' '.CONTACT_FROM.' '.$name_sender : '');
		$message_html = '';
		$message_txt = (isset($_POST['message'])) ? stripslashes($_POST['message']) : '';
	}

	// on verifie si tous les parametres sont bons
	$message = check_parameters_mail($_POST['type'], $mail_sender, $name_sender, $mail_receiver, $subject, $message_txt);

	// si pas d'erreur on envoie
	if($message['class'] == 'success') {
		if (send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html)) {
			if ($_POST['type']=='contact' || $_POST['type']=='mail') {
				$message['message'] = CONTACT_MESSAGE_SUCCESSFULLY_SENT;
			} 
			elseif ($_POST['type']=='abonnement') {
				$message['message'] = CONTACT_SUBSCRIBE_ORDER_SENT;
			}
			elseif ($_POST['type']=='desabonnement') {
			 	$message['message'] = CONTACT_UNSUBSCRIBE_ORDER_SENT;
			 } 
		} 
		else {
			$message['class'] = "error";
			$message['message'] = CONTACT_MESSAGE_NOT_SENT;
		}
	}

	echo '<div class="alert alert-'.$message['class'].'"><button type="button" class="close" data-dismiss="alert">&times;</button>'.$message['message'].'</div>';
}

//sinon on affiche le formulaire d'envoi de mail
else {
	//si on est identifie
	if ($this->GetUser()) {
		//on verifie si l'on est bien identifie comme admin, pour eviter le spam
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
			$output .= '<div class="alert alert-error"><button type="button" class="close" data-dismiss="alert">&times;</button>'.CONTACT_HANDLER_MAIL_FOR_ADMINS.'</div>'."\n";
		}
	}

	//on affiche le formulaire d'identification sinon
	else {
		$output .= '<div class="alert"><button type="button" class="close" data-dismiss="alert">&times;</button>'.CONTACT_HANDLER_MAIL_FOR_ADMINS.'<br />'.CONTACT_LOGIN_IF_ADMIN.'</div>'."\n";
		$output .= $this->Format('{{login}}')."\n";
	}

	//affichage a l'ecran
	echo $this->Header();
	echo "<div class=\"page\">\n$output\n<hr class=\"hr_clear\" />\n</div>\n";
	echo $this->Footer();
}
?>
