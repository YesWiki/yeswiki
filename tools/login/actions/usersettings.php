<?php
/*
usersettings.php
Software under AGPL Licence
*/

if (!defined('WIKINI_VERSION')) {
 die('acc&egrave;s direct interdit');
}

$userLoggedIn = false;
$referrer='';
$isAdmin = $this->UserIsAdmin();
if ($isAdmin && isset($_GET['user']) && ($_GET['user'] != '')) { // We are here because an admin comes to manage another user ($_GET['user']
	$adminIsActing = true;
	$OK = $this->user->loadByNameFromDB($_GET['user']);
	if (!$OK) { // Did not find the user in DB
		$this->session->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER').' !');
	}
	$referrer = $_GET['from'];
} else { // Admin isn't acting
	$adminIsActing = false;
	if ($this->user->loadFromSession()) { // Trying to instanciate $user from the session cooky)
		$userLoggedIn = true;
	}
}

if (isset($_REQUEST['usersettings_action'])) {
	$action = $_REQUEST['usersettings_action'];
	if (!$adminIsActing && (stripos($action, 'ByAdmin'))) { // Didn't notice it was admin acting but the action was requested by admin, therefore it's admin acting
		$adminIsActing = true;
		$userLoggedIn = false;
		$OK = $this->user->loadByEmailFromDB($_REQUEST['email']); // In this case we need to load the right user
		if (!$OK) { // Did not find the user in DB
			$this->session->setMessage(_t('USER_TRYING_TO_MODIFY_AN_INEXISTANT_USER').' !');
		}
	}
} else {
	$action = '';
}

if ($action == 'logout') { // User wants to log out
	$this->user->logOut();
	$this->session->setMessage(_t('USER_YOU_ARE_NOW_DISCONNECTED').' !');
	$this->Redirect($this->href());

} elseif ($adminIsActing || $userLoggedIn) { // Admin or user wants to manage the user
    if (substr ( $action , 0, 6) == 'update') { // Whoever it is tries to update the user
		$OK = $this->user->setByAssociativeArray(array(
			'email'	 			=> isset($_POST['email']) ? $_POST['email'] : '',
			'motto'				=> isset($_POST['motto']) ? $_POST['motto'] : '',
			'revisioncount'  	=> isset($_POST['revisioncount']) ? $_POST['revisioncount'] : '',
			'changescount'		=> isset($_POST['changescount']) ? $_POST['changescount'] : '',
			'doubleclickedit'	=> isset($_POST['doubleclickedit']) ? $_POST['doubleclickedit'] : '',
			'show_comments' 	=> isset($_POST['show_comments']) ? $_POST['show_comments'] : '',
        ));
        if ($OK) {
			$OK = $this->user->updateIntoDB('email, motto, revisioncount, changescount, doubleclickedit, show_comments');
			if ($userLoggedIn) { // In case it's the user trying to update oneself, need to reset the cooky
				$this->user->logIn();
			}
			// forward
			$this->session->setMessage(_t('USER_PARAMETERS_SAVED').' !');
			if ($userLoggedIn) { // In case it's the usther trying to update oneself
				$this->Redirect($this->href());
			} else { // That's the admin acting, we need to pass the user on
				$this->Redirect($this->href('','','user='.$_GET['user'].'&from='.$referrer,false));
			}
		} else { // Unable to update
			$this->session->setMessage($this->user->error);
		}
	} // End of update action

	if ($adminIsActing) { // Admin wants to manage the user

		if ($action == 'deleteByAdmin') { // Admin trying to delete user
			$this->user->delete();
			// forward
			$this->session->setMessage(_t('USER_DELETED').' !');
			$this->Redirect($this->href('',$referrer));
		} // End of delete by admin action

	} elseif ($userLoggedIn) { // Admin isn't acting therefore that's an already logged in user

		if ($action == 'changepass') { // User wants to change password
			if (!$this->user->checkPassword($_POST['oldpass'])) { // check password first
				$error = $this->user->error;
			} else { // user properly typed his old password in
				$password = $_POST['password'];
				if ($this->user->updatePassword($password)) {
					$this->session->setMessage(_t('USER_PASSWORD_CHANGED').' !');
					$this->user->logIn();
					$this->Redirect($this->href());
				} else { // Something when wrong when updating the user in DB
					$this->session->setMessage($this->user->error);
				}
			}
		} // End of changepass action

	} // End of actions performed by a logged in user
?>

<!-- FORM UPDATE (user is logged in; display config form) -->
<h2><?php
	echo _t('USER_SETTINGS');
	if ($adminIsActing) {
		echo ' — '.$this->user->getProperty('name');
	}
?></h2>
<?php
if ($adminIsActing) {
	$href = $this->href('','','user='.$this->user->getProperty('name').'&from='.$referrer,false);
} else {
	$href = $this->href();
}
?>
<form action="<?php echo $href; ?>" method="post" class="form-horizontal">
<?php	// This form submits
		//		either "update" if requested by the logged user
		//		or 	 "updateByAdmin" if requested by an admin
?>
	<input type="hidden" name="usersettings_action" value="update<?php echo $adminIsActing ? 'ByAdmin' : '' ?>" />
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_EMAIL_ADDRESS');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="email" value="<?php echo htmlspecialchars($this->user->getProperty('email'), ENT_COMPAT, YW_CHARSET) ?>" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_MAX_NUMBER_OF_VERSIONS');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="revisioncount" value="<?php echo htmlspecialchars($this->user->getProperty('revisioncount'), ENT_COMPAT, YW_CHARSET) ?>" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<div class="controls col-sm-9 col-sm-offset-3">
			<input class="btn btn-primary" type="submit" value="<?php echo _t('USER_UPDATE');?>" />
<?php
			if ($userLoggedIn) { // The one who runs the session is acting
?>
				<input class="btn btn-warning" type="button" value="<?php echo _t('USER_DISCONNECT');?>" onclick="document.location='<?php echo $this->href('', '', 'usersettings_action=logout');?>'" />
<?php
			} // End of the one who runs the session is acting
?>
		</div>
	</div>
<?php echo $this->FormClose();	 ?>

<!-- FORM DELETE -->
<?php
			if ($adminIsActing) { // Admin is acting
?>
<form action="<?php echo $this->href('','','user='.$this->user->getProperty('name').'&from='.$referrer, false); ?>" method="post" class="form-horizontal">
	<input type="hidden" name="usersettings_action" value="deleteByAdmin" />
	<input class="btn btn-danger" type="submit" value="<?php echo _t('USER_DELETE');?>" />
<?php echo $this->FormClose();
			} // End of Admin is acting
?>


<!-- FORM CHANGE PASSWORD  -->
<?php
if ($userLoggedIn) { // The one who runs the session is acting
?>
<form action="<?php echo $this->href(); ?>" method="post" class="form-horizontal">
	<hr>
	<input type="hidden" name="usersettings_action" value="changepass" />
	<h2><?php echo _t('USER_CHANGE_THE_PASSWORD');?></h2>
	<?php if (isset($error)) echo '<div class="alert alert-danger">', $error, "</div>\n"; ?>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_OLD_PASSWORD');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" type="password" name="oldpass" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_NEW_PASSWORD');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" type="password" name="password" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<div class="controls col-sm-9 col-sm-offset-3">
			<input class="btn btn-primary" type="submit" value="<?php echo _t('USER_CHANGE');?>" size="40" />
		</div>
	</div>
<?php
	echo $this->FormClose();
} // End of the one who runs the session is acting
} else { // Neither logged in user nor admin trying to do something

if ($action == 'login') { // user is trying to log in or register
	if ($this->user->loadByNameFromDB($_POST['name'])) { // if user name already exists, check password
		if ($this->user->checkPassword($_POST['password'])) { // Password is correct
			$this->user->logIn($_POST['remember']);
			$this->Redirect($this->href('', '', 'usersettings_action=checklogged', false));
		} else { // Password is not correct
			$error = $this->user->error;
		}
	} else { // user does not exist in DB, therefore it's a new user registration
		if ($this->user->passwordIsIncorrect($_POST['password'], $_POST['confpassword'])) {
			$error = $this->user->error;
		} else { // Password is correct
			if ($this->user->setByAssociativeArray(array(
				'name'				=> trim($_POST['name']),
				'email'				=> trim($_POST['email']),
				'password'			=> $_POST['password'],
				'revisioncount'	=> 20,
				'changescount'		=> 0,
				'doubleclickedit'	=> 'Y',
				'show_comments'	=> 'N',
			))) { // User properties set without any problem
                echo 'toto';exit;
                if ($this->user->createIntoDB()) { // No problem with user creation in DB
					$this->user->logIn();
					$this->Redirect($this->href()); // forward
				} else { // PB while creating user in DB
					$error = $this->user->error;
				}
			} else { // We had problems with the properties setting
				$error = $this->user->error;
			}
		}
	} // end of new user registration
} elseif ($action == 'checklogged') {
	 $error = _t('USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
} ?>

<!-- FORM SIGN UP -->
<h2><?php echo _t('USER_SIGN_UP');?></h2>
<form action="<?php echo $this->href(); ?>" method="post" class="form-horizontal">
	<input type="hidden" name="usersettings_action" value="login" />
	<?php if (isset($error)) echo '<div class="alert alert-danger">', $error, "</div>\n"; ?>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_WIKINAME');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="name" size="40" value="<?php
				if ($name = $this->user->getProperty('name')){ // If $user object exists, we can get his or her name
					echo htmlspecialchars($name, ENT_COMPAT, YW_CHARSET);
				}?>" />
		</div>
 	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_EMAIL_ADDRESS');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="email" size="40" value="<?php
				if ($email = $this->user->getProperty('email')) { // If $user object exists, we can get his or her email
					 echo htmlspecialchars($email, ENT_COMPAT, YW_CHARSET);
				}
				?>" />
		</div>
	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_PASSWORD');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" type="password" name="password" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_PASSWORD_CONFIRMATION');?></label>
		<div class="controls col-sm-9">
			<input class="form-control" type="password" name="confpassword" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<div class="controls col-sm-9 col-sm-offset-3">
			<input class="btn btn-block btn-primary" type="submit" value="<?php echo _t('USER_NEW_ACCOUNT');?>" size="40" />
		</div>
	</div>
<?php echo $this->FormClose(); ?>

<hr>

<button class="btn btn-block btn-default" onclick="$('a[href=\'#LoginModal\']').click()">
<?php echo _t('LOGIN_LOGIN'); ?>
<?php }  // End of neither logged in user nor admin trying to do something ?>
