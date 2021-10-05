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
    if (substr($action, 0, 6) == 'update') { // Whoever it is tries to update the user
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
                $this->Redirect($this->href('', '', 'user='.$_GET['user'].'&from='.$referrer, false));
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
            $this->Redirect($this->href('', $referrer));
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
    } // End of actions performed by a logged in user ?>

<!-- FORM UPDATE (user is logged in; display config form) -->
<h2><?php
    echo _t('USER_SETTINGS');
    if ($adminIsActing) {
        echo ' — '.$this->user->getProperty('name');
    } ?></h2>
<?php
if ($adminIsActing) {
        $href = $this->href('', '', 'user='.$this->user->getProperty('name').'&from='.$referrer, false);
    } else {
        $href = $this->href();
    } ?>
<form action="<?php echo $href; ?>" method="post" class="form-horizontal">
<?php	// This form submits
        //		either "update" if requested by the logged user
        //		or 	 "updateByAdmin" if requested by an admin
?>
	<input type="hidden" name="usersettings_action" value="update<?php echo $adminIsActing ? 'ByAdmin' : '' ?>" />
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_EMAIL_ADDRESS'); ?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="email" value="<?php echo htmlspecialchars($this->user->getProperty('email'), ENT_COMPAT, YW_CHARSET) ?>" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<label class="control-label col-sm-3"><?php echo _t('USER_MAX_NUMBER_OF_VERSIONS'); ?></label>
		<div class="controls col-sm-9">
			<input class="form-control" name="revisioncount" value="<?php echo htmlspecialchars($this->user->getProperty('revisioncount'), ENT_COMPAT, YW_CHARSET) ?>" size="40" />
		</div>
	</div>
	<div class="control-group form-group">
		<div class="controls col-sm-9 col-sm-offset-3">
			<input class="btn btn-primary" type="submit" value="<?php echo _t('USER_UPDATE'); ?>" />
<?php
            if ($userLoggedIn) { // The one who runs the session is acting
?>
				<input class="btn btn-warning" type="button" value="<?php echo _t('USER_DISCONNECT');?>" onclick="document.location='<?php echo $this->href('', '', 'action=logout');?>'" />
<?php
            } // End of the one who runs the session is acting
?>
		</div>
	</div>
<?php echo $this->FormClose(); ?>

<!-- FORM DELETE -->
<?php
            if ($adminIsActing) { // Admin is acting
?>
<form action="<?php echo $this->href('', '', 'user='.$this->user->getProperty('name').'&from='.$referrer, false); ?>" method="post" class="form-horizontal">
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
	<?php if (isset($error)) {
    echo '<div class="alert alert-danger">', $error, "</div>\n";
} ?>
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
    // sanitize $_POST['name']
    if (isset($_POST['name'])){
        $_POST['name'] = htmlspecialchars($_POST['name']);
    }
    if ($action == 'signup') { // user is trying to register
        if (!$this->user->passwordIsCorrect($_POST['password'], $_POST['confpassword'])) {
            $error = $this->user->error;
        } else { // Password is correct
            if ($this->user->setByAssociativeArray(
                array(
                    'name'				=> trim($_POST['name']),
                    'email'				=> trim($_POST['email']),
                    'password'			=> md5($_POST['password']),
                    'revisioncount'	    => 20,
                    'changescount'		=> 100,
                    'doubleclickedit'	=> 'Y',
                    'show_comments'	    => 'N',
                )
            )) { // User properties set without any problem
                if ($this->user->createIntoDB()) { // No problem with user creation in DB
                    $this->user->logIn();
                    $this->Redirect($this->href()); // forward
                } else { // PB while creating user in DB
                    $error = $this->user->error;
                }
            } else { // We had problems with the properties setting
                $error = $this->user->error;
            }
        } // end of new user registration
    } elseif ($action == 'checklogged') {
        $error = _t('USER_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
    }

    $name = htmlspecialchars(!empty($_POST['name']) ? $_POST['name'] : $this->user->getProperty('name'), ENT_COMPAT, YW_CHARSET);
    $email = htmlspecialchars(!empty($_POST['email']) ? $_POST['email'] : $this->user->getProperty('email'), ENT_COMPAT, YW_CHARSET);
    
    echo $this->render("@login/user-signup-form.tpl.html", [
        "link" => $this->href(),
        "error" => !empty($error) ? $error : '',
        "name" => $name,
        "email" => $email
    ]);
}  // End of neither logged in user nor admin trying to do something?>
