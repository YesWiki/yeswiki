<?php
/*
 * lostpassword.php
 *
 * License AGPL.
 *
 */
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if (!defined('PW_SALT')) {
    define('PW_SALT', 'FBcA');
}

require_once 'includes/User.class.php';

$error = false;
$step = 'emailForm'; // Formulaire par defaut
$user = new \YesWiki\User($this);

if (isset($_POST['subStep']) && !isset($_GET['a'])) { // Sub step
	switch ($_POST['subStep']) {
		case 1:
			// we just submitted an email or username for verification
			$error = !($user->loadByEmailFromDB($_POST['email']));
			if ($error) {
				$step = 'userNotFound';
			} else {
				$step = 'successPage';
				$user->sendPasswordRecoveryEmail();
			}
			break;
		case 2:
			// we are submitting a new password (only for encrypted)
			if ($_POST['userID'] == '' || $_POST['key'] == '') {
				header('location: login.php');
			}
			if ((strcmp($_POST['pw0'], $_POST['pw1']) != 0) || (trim($_POST['pw0']) == '')) { // No pw0 or different pwd
				$error = true;
				$user->loadByNameFromDB($_POST['userID']);
				$step = 'recoverForm';
			} else {
				$error = false;
				$step = 'recoverSuccess';
				if ($user->loadByNameFromDB($_POST['userID'])) {
					if ($user->resetPassword($_POST['userID'], $_POST['key'], $_POST['pw0'])) { // entails password recovery key ckeck
						$user->logIn(); // Set session cookie
					} else { // Not able to update password
						$error = true;
					}
				} else { // Not able to load the user from DB
					$error = true;
				}
			}
			break;
		} // End switch
} elseif (isset($_GET['a']) && $_GET['a'] == 'recover' && $_GET['email'] != '') {
    $step = 'invalidKey';
    $result = $user->checkEmailKey($_GET['email'], $_GET['u']);
    if ($result == false) {
        $error = true;
        $step = 'invalidKey';
    } else {
        $error = false;
        $user->loadByNameFromDB(base64_decode($_GET['u']));
        $step = 'recoverForm';
    }
}

echo '<h2>'._t('LOGIN_CHANGE_PASSWORD').'</h2>';
switch ($step) {
    case 'userNotFound':
        echo '<div class="alert alert-danger">'._t('LOGIN_UNKNOWN_USER').'</div>'."\n"
          .'<a href="'.$this->href('', $this->GetPageTag()).'" class="btn btn-default">'._t('LOGIN_BACK').'</a>';
        break;

    case 'emailForm':
        echo $this->FormOpen('', '', 'post', 'form-horizontal');
        if ($error == true) {
            echo '<div class="alert alert-danger">'._t('LOGIN_ADD_EMAIL_TO_CONTINUE').'.</div>'."\n";
        } ?>
    <div class="control-group form-group">
      <label class="control-label col-sm-3" for="email"><?php echo _t('LOGIN_EMAIL'); ?></label>
      <div class="controls col-sm-9">
          <input type="email" class="form-control" name="email" required value="" placeholder="<?php echo _t('LOGIN_EMAIL'); ?>">
      </div>
    </div>
    <div class="control-group form-group">
        <input type="hidden" name="subStep" value="1" />
        <div class="controls col-sm-9 col-sm-offset-3">
            <button type="submit" class="btn btn-primary"><?php echo _t('LOGIN_SEND'); ?></button>
        </div>
    </div>
<?php
        echo $this->FormClose();
        break;

    case 'successPage':
        echo '<div class="alert alert-success">'._t('LOGIN_MESSAGE_SENT').'.</div>'."\n";
        break;

    case 'recoverForm':
        echo '<p class="welcome-text">'
          .'<strong>'._t('LOGIN_WELCOME').' '.$user->getName().'</strong><br />'
          ._t('LOGIN_WRITE_PASSWORD')
          .'.</p>'."\n";
        if ($error == true) {
            echo '<div class="alert alert-danger">'._t('LOGIN_PASSWORD_SHOULD_BE_IDENTICAL').'.</div>'."\n";
        }
        echo $this->FormOpen();
    ?>
<div class="form-group">
  <label for="pw0"><?php echo _t('LOGIN_NEW_PASSWORD'); ?></label>
  <input type="password" class="form-control" name="pw0" id="pw0" value="" maxlength="40" required>
</div>
<div class="form-group">
  <label for="pw1"><?php echo _t('LOGIN_CONFIRM_PASSWORD'); ?></label>
  <input type="password" class="form-control" name="pw1" id="pw1" value="" maxlength="40" required>
</div>
<input type="hidden" name="subStep" value="2" />
<input type="hidden" name="userID" value="<?php echo (empty($user->getName()) ? $_POST['userID'] : $user->getName()); ?>" />
<input type="hidden" name="key" value="<?php echo (!empty($_GET['email']) ? $_POST['key'] : $_GET['email']); ?>" />
<button type="submit" class="btn btn-primary"><?php echo _t('LOGIN_SEND'); ?></button>
<?php
        echo $this->FormClose();
        break;
    case 'invalidKey':
        echo '<div class="alert alert-danger">'._t('LOGIN_INVALID_KEY').'.</div>'."\n";
        break;
    case 'recoverSuccess':
        echo '<div class="alert alert-success">'._t('LOGIN_PASSWORD_WAS_RESET').'.</div>'."\n";
        break;
}
