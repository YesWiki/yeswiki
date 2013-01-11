<?php

require_once('tools/contact/libs/Mail.php');
require_once('tools/contact/libs/Mail/mime.php');

function ValidateEmail($email) {
	$regex = "([a-z0-9_\.\-]+)". # name
			"@". # at
			"([a-z0-9\.\-]+){2,255}". # domain & possibly subdomains
			"\.". # period
			"([a-z]+){2,10}"; # domain extension
	$eregi = eregi_replace($regex, '', $email);
	return empty($eregi) ? true : false;
}

function check_parameters_mail($type, $mail_sender, $name_sender, $mail_receiver, $subject, $message) {
	$error = '';

	// Check sender's name
	if($type=='contact' && !$name_sender) {
		$error['contact-name'] = 'Vous devez entrer un nom.<br />';
	}

	// Check sender's email
	if(!$mail_sender) {
		$error['contact-mail'] = 'Vous devez entrer une adresse mail pour l\'exp&eacute;diteur.<br />';
	}
	if($mail_sender && !ValidateEmail($mail_sender)) {
		$error['contact-mail'] = 'L\'adresse mail de l\'exp&eacute;diteur n\'est pas valide.<br />';
	}

	// Check the receiver's email
	if(!$mail_receiver) {
		$error['mail'] = 'Vous devez entrer une adresse mail pour le destinataire.<br />';
	}
	if($mail_receiver && !ValidateEmail($mail_receiver)) {
		$error['mail'] = 'L\'adresse mail du destinaire n\'est pas valide.<br />';
	}

	// Check message (length)
	if($type=='contact' && (!$message || strlen($message) < 10)) {
		$error['contact-message'] = "Veuillez entrer un message. Il doit faire au minimum 10 caract&egrave;res.<br />";
	}

	return $error;
}

function send_mail($mail_sender, $name_sender, $mail_receiver, $subject, $message_txt, $message_html = '', $output_success = 'OK', $output_fail = 'FAIL') {

	$headers['From']    = $mail_sender;
	$headers['To']      = $mail_sender;
	$headers['Subject'] = $subject;
	if ($message_html != '') {
		$mime = new Mail_mime("\n");
		$mime->setTXTBody($message_txt);
		$mime->setHTMLBody($message_html);
		$message = $mime->get();
		$headers = $mime->headers($headers);
	}
	else {
		$message = $message_txt;
	}
	// Creer un objet mail en utilisant la methode Mail::factory.
	$object_mail = & Mail::factory(CONTACT_MAIL_FACTORY);

	if($object_mail->send($mail_receiver, $headers, $message))	{
		return $output_success;
	}
	else {
		return $output_fail;
	}
}

?>
