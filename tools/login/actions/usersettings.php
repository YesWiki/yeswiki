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
}?>

<!-- FORM UPDATE (user is logged in; display config form) -->
<h2><?php echo _t('USER_SETTINGS');?></h2>
<?php echo $this->FormOpen('', '', 'post', 'form-horizontal'); ?>

   <input type="hidden" name="usersettings_action" value="update" />
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('YOUR_EMAIL_ADDRESS');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_COMPAT, YW_CHARSET) ?>" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('DOUBLE_CLICK_TO_EDIT');?></label>
      <div class="controls col-sm-9">
         <input type="hidden" name="doubleclickedit" value="N" />
         <label>
            <input type="checkbox" name="doubleclickedit" value="Y" <?php echo $user['doubleclickedit'] == 'Y' ? 'checked="checked"' : '' ?> />
            <span></span>
         </label>
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('SHOW_COMMENTS_BY_DEFAULT');?></label>
      <div class="controls col-sm-9">
         <input type="hidden" name="show_comments" value="N" />
         <label>
            <input type="checkbox" name="show_comments" value="Y" <?php echo $user['show_comments'] == 'Y' ? 'checked="checked"' : '' ?> />
            <span></span>
         </label>
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('MAX_NUMBER_OF_LASTEST_COMMENTS');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="changescount" value="<?php echo htmlspecialchars($user['changescount'], ENT_COMPAT, YW_CHARSET) ?>" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('MAX_NUMBER_OF_VERSIONS');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="revisioncount" value="<?php echo htmlspecialchars($user['revisioncount'], ENT_COMPAT, YW_CHARSET) ?>" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('YOUR_MOTTO');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="motto" value="<?php echo htmlspecialchars($user['motto'], ENT_COMPAT, YW_CHARSET) ?>" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <div class="controls col-sm-9 col-sm-offset-3">
         <input class="btn btn-primary" type="submit" value="<?php echo _t('UPDATE');?>" />
         <input class="btn btn-warning" type="button" value="<?php echo _t('DISCONNECT');?>" onclick="document.location='<?php echo $this->href('', '', 'usersettings_action=logout');?>'" />
      </div>
   </div>
<?php echo $this->FormClose();    ?>


<!-- FORM CHANGE PASSWORD  -->
<?php echo $this->FormOpen('', '', 'post', 'form-horizontal'); ?>
   <hr>
   <input type="hidden" name="usersettings_action" value="changepass" />
   <h2><?php echo _t('CHANGE_THE_PASSWORD');?></h2>
   <?php if (isset($error)) echo '<div class="alert alert-danger">', $error, "</div>\n"; ?>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('YOUR_OLD_PASSWORD');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" type="password" name="oldpass" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('NEW_PASSWORD');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" type="password" name="password" size="40" />
      </div>
   </div>
   <div class="control-group form-group">
      <div class="controls col-sm-9 col-sm-offset-3">
         <input class="btn btn-primary" type="submit" value="<?php echo _t('CHANGE');?>" size="40" />
      </div>
   </div>
<?php echo $this->FormClose(); ?>

<?php
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
         $error = _t('WRONG_PASSWORD').'!';
        }
    } else {
        // otherwise, create new account
     $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confpassword = $_POST['confpassword'];

        // check if name is WikkiName style
        if (!$this->IsWikiName($name, WN_CAMEL_CASE_EVOLVED)) {
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
} ?>

<!-- FORM SIGN UP -->
<h2><?php echo _t('USER_SIGN_UP');?></h2>
<?php echo $this->FormOpen('', '', 'post', 'form-horizontal'); ?>

   <input type="hidden" name="usersettings_action" value="login" />
   <?php if (isset($error)) echo '<div class="alert alert-danger">', $error, "</div>\n"; ?>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('YOUR_WIKINAME');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="name" size="40" value="<?php
            if (isset($name)) echo htmlspecialchars($name, ENT_COMPAT, YW_CHARSET);?>" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('YOUR_EMAIL_ADDRESS');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" name="email" size="40" value="<?php
            if (isset($email)) {
                echo htmlspecialchars($email, ENT_COMPAT, YW_CHARSET);
            }
            ?>" />
      </div>
   </div>
   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('PASSWORD_5_CHARS_MINIMUM');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" type="password" name="password" size="40" />
      </div>
   </div>

   <div class="control-group form-group">
      <label class="control-label col-sm-3"><?php echo _t('PASSWORD_CONFIRMATION');?></label>
      <div class="controls col-sm-9">
         <input class="form-control" type="password" name="confpassword" size="40" />
      </div>
   </div>

   <div class="control-group form-group">
      <div class="controls col-sm-9 col-sm-offset-3">
         <input class="btn btn-primary" type="submit" value="<?php echo _t('NEW_ACCOUNT');?>" size="40" />
      </div>
   </div>
<?php echo $this->FormClose(); ?>

<?php } ?>
