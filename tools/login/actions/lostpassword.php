<?php
/*
 * lostpassword.php
 * 2013 David Delon
 *
 * License GPL.
 *
 */
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if (!defined('PW_SALT')) {
    define('PW_SALT', 'FBcA');
}

include_once 'tools/login/libs/login.functions.php';

$error = false;
$step = 'emailForm'; // Formulaire par defaut

if (isset($_POST['subStep']) && !isset($_GET['a'])) { // Sous-etape
    switch ($_POST['subStep']) {
        case 1:
            // we just submitted an email or username for verification
            $result= checkUNEmail($_POST['email']);
            if ($result['status'] == false) {
                $error = true;
                $step = 'userNotFound';
            } else {
                $error = false;
                $step = 'successPage';
                $securityUser = $result['userID'];
                sendPasswordEmail($securityUser);
            }
            break;
        case 2:
            // we are submitting a new password (only for encrypted)
            if ($_POST['userID'] == '' || $_POST['key'] == '') {
                header('location: login.php');
            }
            if (strcmp($_POST['pw0'], $_POST['pw1']) != 0 || trim($_POST['pw0']) == '') {
                $error = true;
                $securityUser = $_POST['userID'];
                $step = 'recoverForm';
            } else {
                $error = false;
                $step = 'recoverSuccess';
                if (updateUserPassword($_POST['userID'], $_POST['pw0'], $_POST['key'])) { // il y encore un controle ici
                    $this->SetUser($this->LoadUser($_POST['userID'])); // on s'identitifie
                }
            }
            break;
    }
} elseif (isset($_GET['a']) && $_GET['a'] == 'recover' && $_GET['email'] != '') {
    $step = 'invalidKey';
    $result= checkEmailKey($_GET['email'], urldecode(base64_decode($_GET['u'])));
    if ($result== false) {
        $error = true;
        $step = 'invalidKey';
    } elseif ($result['status'] == true) {
        $error = false;
        $step = 'recoverForm';
        $securityUser = $result['userID'];
    }
}

echo '<h4>'._t('LOGIN_CHANGE_PASSWORD').'</h4>';
switch ($step) {
    case 'userNotFound':
        echo '<div class="alert alert-danger">'._t('LOGIN_UNKNOWN_USER').'</div>'."\n"
          .'<a href="'.$this->href('', $this->GetPageTag()).'" class="btn btn-default">'._t('LOGIN_BACK').'</a>';
        break;

    case 'emailForm':
        echo $this->FormOpen('', '', 'post', 'form-inline');
        if ($error == true) {
            echo '<div class="alert alert-danger">'._t('LOGIN_ADD_EMAIL_TO_CONTINUE').'.</div>'."\n";
        } ?>
    <div class="form-group">
      <label for="email"><?php echo _t('LOGIN_EMAIL'); ?></label>
      <input type="email" class="form-control" name="email" required value="" placeholder="<?php echo _t('LOGIN_EMAIL'); ?>">
    </div>
<input type="hidden" name="subStep" value="1" />
<button type="submit" class="btn btn-primary"><?php echo _t('LOGIN_SEND'); ?></button>
<?php
        echo $this->FormClose();
        break;

    case 'successPage':
        echo '<div class="alert alert-success">'._t('LOGIN_MESSAGE_SENT').'.</div>'."\n";
        break;

    case 'recoverForm':
        echo '<h5>'._t('LOGIN_WELCOME').' '.$securityUser.'</h5>'."\n";
        echo '<p>'._t('LOGIN_WRITE_PASSWORD').'.</p>'."\n";
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
<input type="hidden" name="userID" value="<?php echo $securityUser == '' ? $_POST['userID'] : $securityUser; ?>" />
<input type="hidden" name="key" value="<?php echo (!isset($_GET['email']) || $_GET['email'] == '') ? $_POST['key'] : $_GET['email']; ?>" />
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
