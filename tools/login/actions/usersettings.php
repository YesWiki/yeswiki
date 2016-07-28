<?php
/*
usersettings.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002  Patrick PAUL
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

if (!defined('WIKINI_VERSION')) {
	die('acc&egrave;s direct interdit');
}

echo $this->Format('====='._t('USER_SETTINGS').'=====');

$action = isset($_REQUEST['usersettings_action']) ? $_REQUEST['usersettings_action'] : '' ;

if ($action == 'logout') {

    $this->LogoutUser();
    $this->SetMessage(_t('YOU_ARE_NOW_DISCONNECTED').' !');
    $this->Redirect($this->href());

} elseif ($user = $this->GetUser()) {

    // is user trying to update?
    if ($action == 'update') {
        $this->Query('update '.$this->getUserTablePrefix().'users set '.
            "email = '".mysqli_real_escape_string($this->dblink, $_POST['email'])."', ".
            "doubleclickedit = '".mysqli_real_escape_string($this->dblink, $_POST['doubleclickedit'])."', ".
            "show_comments = '".mysqli_real_escape_string($this->dblink, $_POST['show_comments'])."', ".
            "revisioncount = '".mysqli_real_escape_string($this->dblink, $_POST['revisioncount'])."', ".
            "changescount = '".mysqli_real_escape_string($this->dblink, $_POST['changescount'])."', ".
            "motto = '".mysqli_real_escape_string($this->dblink, $_POST['motto'])."' ".
            "where name = '".$user['name']."' limit 1");

        $this->SetUser($this->LoadUser($user['name']));

        // forward
        $this->SetMessage(_t('PARAMETERS_SAVED').' !');
        $this->Redirect($this->href());
    }

    if ($action == 'changepass') {
        // check password
            $password = $_POST['password'];
        if (preg_match('/ /', $password)) {
            $error = _t('NO_SPACES_IN_PASSWORD').'.';
        } elseif (strlen($password) < 5) {
            $error = _t('PASSWORD_TOO_SHORT').'.';
        } elseif ($user['password'] != md5($_POST['oldpass'])) {
            $error = _t('WRONG_PASSWORD').'.';
        } else {
            $this->Query('update '.$this->getUserTablePrefix().'users set '."password = md5('".mysqli_real_escape_string($this->dblink, $password)."') "."where name = '".$user['name']."'");
            $this->SetMessage(_t('PASSWORD_CHANGED').' !');
            $user['password'] = md5($password);
            $this->SetUser($user);
            $this->Redirect($this->href());
        }
    }
    // user is logged in; display config form
    echo $this->FormOpen();
    ?>
  <input type="hidden" name="usersettings_action" value="update" />
  <table>
    <tr>
      <td align="right"></td>
      <td><?php echo _t('GREETINGS');?>, <?php echo $this->Link($user['name']) ?>&nbsp;!</td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('YOUR_EMAIL_ADDRESS');?>&nbsp;:</td>
      <td><input name="email" value="<?php echo htmlspecialchars($user['email'], ENT_COMPAT, YW_CHARSET) ?>" size="40" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('DOUBLE_CLICK_TO_EDIT');?>&nbsp;:</td>
      <td><input type="hidden" name="doubleclickedit" value="N" /><input type="checkbox" name="doubleclickedit" value="Y" <?php echo $user['doubleclickedit'] == 'Y' ? 'checked="checked"' : '' ?> /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('SHOW_COMMENTS_BY_DEFAULT');?>&nbsp;:</td>
      <td><input type="hidden" name="show_comments" value="N" /><input type="checkbox" name="show_comments" value="Y" <?php echo $user['show_comments'] == 'Y' ? 'checked"checked"' : '' ?> /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('MAX_NUMBER_OF_LASTEST_COMMENTS');?>&nbsp;:</td>
      <td><input name="changescount" value="<?php echo htmlspecialchars($user['changescount'], ENT_COMPAT, YW_CHARSET) ?>" size="40" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('MAX_NUMBER_OF_VERSIONS');?>&nbsp;:</td>
      <td><input name="revisioncount" value="<?php echo htmlspecialchars($user['revisioncount'], ENT_COMPAT, YW_CHARSET) ?>" size="40" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('YOUR_MOTTO');?>&nbsp;:</td>
      <td><input name="motto" value="<?php echo htmlspecialchars($user['motto'], ENT_COMPAT, YW_CHARSET) ?>" size="40" /></td>
    </tr>
    <tr>
      <td></td>
      <td>
      	<input type="submit" value="<?php echo _t('UPDATE');?>" />
      	<input type="button" value="<?php echo _t('DISCONNECT');?>" onclick="document.location='<?php echo $this->href('', '', 'usersettings_action=logout');?>'" />
      </td>
    </tr>
  </table>
	<?php
    echo $this->FormClose();
    echo $this->FormOpen();
	?>
  <input type="hidden" name="usersettings_action" value="changepass" />
  <table>
    <tr>
      <td>&nbsp;</td>
      <td>&nbsp;</td>
    </tr>
    <tr>
      <td align="right"></td>
      <td><?php echo _t('CHANGE_THE_PASSWORD');?></td>
    </tr>
    <?php
    if (isset($error)) {
        echo '<tr><td></td><td><div class="alert alert-danger">', $error, "</div></td></tr>\n";
    }
    ?>
    <tr>
      <td align="right"><?php echo _t('YOUR_OLD_PASSWORD');?>&nbsp;:</td>
      <td><input type="password" name="oldpass" size="40" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('NEW_PASSWORD');?>&nbsp;:</td>
      <td><input type="password" name="password" size="40" /></td>
    </tr>
    <tr>
      <td></td>
      <td><input type="submit" value="<?php echo _t('CHANGE');?>" size="40" /></td>
    </tr>
  </table>
    <?php
    echo $this->FormClose();
    
} else {
    // user is not logged in

    // is user trying to log in or register?
    if ($action == 'login') {
        // if user name already exists, check password
        if ($existingUser = $this->LoadUser($_POST['name'])) {
            // check password
            if ($existingUser['password'] == md5($_POST['password'])) {
                $this->SetUser($existingUser, $_POST['remember']);
                $this->Redirect($this->href('', '', 'usersettings_action=checklogged', false));
            } else {
            	$error = _t('WRONG_PASSWORD').'&nbsp;!';
            }
        } else {
            // otherwise, create new account
        	$name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $confpassword = $_POST['confpassword'];

            // check if name is WikkiName style
            if (!$this->IsWikiName($name)) {
            	$error = _t('USERNAME_MUST_BE_WIKINAME').'.';
            } elseif (!$email) {
                $error = _t('YOU_MUST_SPECIFY_AN_EMAIL').'.';
            } elseif (!preg_match("/^.+?\@.+?\..+$/", $email)) {
                $error = _t('THIS_IS_NOT_A_VALID_EMAIL').'.';
            } elseif ($confpassword != $password) {
                $error = _t('PASSWORDS_NOT_IDENTICAL').'.';
            } elseif (preg_match('/ /', $password)) {
                $error = _t('NO_SPACES_IN_PASSWORD').'.';
            } elseif (strlen($password) < 5) {
                $error = _t('PASSWORD_TOO_SHORT').'. '._t('PASSWORD_SHOULD_HAVE_5_CHARS_MINIMUM').'.';
            } else {
                $this->Query('insert into '.$this->getUserTablePrefix().'users set '.
                    'signuptime = now(), '.
                    "name = '".mysqli_real_escape_string($this->dblink, $name)."', ".
                    "email = '".mysqli_real_escape_string($this->dblink, $email)."', ".
                    "motto = '', ".
                    "password = md5('".mysqli_real_escape_string($this->dblink, $_POST['password'])."')");

                // log in
                $this->SetUser($this->LoadUser($name));

                // forward
                $this->Redirect($this->href());
            }
        }
    } elseif ($action == 'checklogged') {
        $error = _t('YOU_MUST_ACCEPT_COOKIES_TO_GET_CONNECTED').'.';
    }

    echo $this->FormOpen();
    ?>
  <input type="hidden" name="usersettings_action" value="login" />
  <table>
    <?php
    if (isset($error)) {
        echo '<tr><td></td><td><div class="alert alert-danger">', $error, "</div></td></tr>\n";
    }
    ?>
    <tr>
      <td align="right"><?php echo _t('YOUR_WIKINAME');?>&nbsp;:</td>
      <td><input name="name" size="40" value="<?php
        if (isset($name)) {
            echo htmlspecialchars($name, ENT_COMPAT, YW_CHARSET);
        } ?>" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('PASSWORD_5_CHARS_MINIMUM');?>&nbsp;:</td>
      <td>
      <input type="password" name="password" size="40" />
      <input type="hidden" name="remember" value="0" />
      <input type="checkbox" name="remember" value="1" />&nbsp;<?php echo _t('REMEMBER_ME');?>.
      </td>
    </tr>
    <tr>
      <td></td>
      <td><?php echo _t('IF_YOU_ARE_REGISTERED_LOGGIN_HERE');?></td>
    </tr>
    <tr>
      <td></td>
      <td><input type="submit" value="<?php echo _t('IDENTIFICATION');?>" size="40" /></td>
    </tr>
    <tr>
      <td></td>
      <td><?php echo _t('FILL_THE_NEXT_FIELDS_IF_YOU_LOGGIN_FOR_THE_FIRST_TIME_AND_REGISTER');?></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('PASSWORD_CONFIRMATION');?>&nbsp;:</td>
      <td><input type="password" name="confpassword" size="40" /></td>
    </tr>
    <tr>
      <td align="right"><?php echo _t('YOUR_EMAIL_ADDRESS');?>.&nbsp;:</td>
      <td><input name="email" size="40" value="<?php
        if (isset($email)) {
            echo htmlspecialchars($email, ENT_COMPAT, YW_CHARSET);
        }
    ?>" /></td>
    </tr>
    <tr>
      <td></td>
      <td><input type="submit" value="<?php echo _t('NEW_ACCOUNT');?>" size="40" /></td>
    </tr>
  </table>
    <?php echo $this->FormClose();
}
?>
