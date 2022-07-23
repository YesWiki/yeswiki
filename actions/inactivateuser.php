<?php
/**
 * inactivateuser.php
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
	
	// Retrieve user manger and triple store for further use

	$vUserManager = $this->services->get(UserManager::class);
	
	// Retrieve user and key from action parameters

	$vUser = $_GET [ACTIONPARAMETER_INACTIVATEUSER_USER];
	$vKey = urldecode ($_GET [ACTIONPARAMETER_INACTIVATEUSER_KEY]);

	// Try to activate the account
	
	if ($vUserManager->inactivateUser ($vUser, $vKey))
	{
		echo ('<div class="alert alert-success">The user account was inactivated successfully.</div>');
	}
	else
	{
		echo ('<div class="alert alert-danger">Cannot inactivate the user account.</div>');
	}
			
?>
