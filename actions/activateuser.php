<?php
/**
 * activateuser.php
 *
 * parameters :
 *		user=user
 *		key= key
 * Author : Yves Gufflet
 *
*/

	if (!defined('WIKINI_VERSION')) {
		die('acc&egrave;s direct interdit');
	}

	use YesWiki\Core\Service\UserManager;
	use YesWiki\Core\Service\Mailer;

	// Retrieve user manger and triple store for further use

	$vUserManager = $this->services->get(UserManager::class);
	
	// Retrieve user and key from action parameters

	$vUser = $_GET [ACTIONPARAMETER_ACTIVATEUSER_USER];
	$vKey = urldecode ($_GET [ACTIONPARAMETER_ACTIVATEUSER_KEY]);

	// Try to activate the account
	
	if ($vUserManager->activateUser ($vUser, $vKey))
	{	
		echo ('<div class="alert alert-success">The account was activated successfully. You can now log in using your login credentials.</div>');

		$vUserMail = $vUserManager->getOneByName ($vUser)["email"];
		$vMailer = $this->services->get(Mailer::class);
		$vMailer->notifyNewUser($vUser, $vUserMail);
	}
	else
	{
		echo ('<div class="alert alert-danger">Cannot activate the user account.</div>');
	}
		
?>
