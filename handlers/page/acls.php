<?php
/*
$Id: acls.php 833 2007-08-10 01:16:57Z gandon $
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright  2003  Eric FELDSTEIN
Copyright  2003  Jean-Pascal MILCENT
Copyright  2004  Jean Christophe ANDRé
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

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

ob_start();
?>
<div class="page">
<?php
if ($this->page && ($this->UserIsOwner() || $this->UserIsAdmin())) {
    if ($_POST) {
        // store lists
        $this->SaveAcl($this->GetPageTag(), "read", $_POST["read_acl"]);
        $this->SaveAcl($this->GetPageTag(), "write", $_POST["write_acl"]);
        $this->SaveAcl($this->GetPageTag(), "comment", ($this->page['comment_on'] ? "" : $_POST["comment_acl"]));
        $message = _t('YW_ACLS_UPDATED');

        // change owner?
        if ($newowner = $_POST["newowner"]) {
            $this->SetPageOwner($this->GetPageTag(), $newowner);
            $message .= _t('YW_NEW_OWNER').$newowner;
        }

        // redirect back to page
        $this->SetMessage($message.' !');
        $this->Redirect($this->Href());
    } else {
        // load acls
        $readACL = $this->LoadAcl($this->GetPageTag(), "read");
        $writeACL = $this->LoadAcl($this->GetPageTag(), "write");
        $commentACL = $this->LoadAcl($this->GetPageTag(), "comment");

        // show form
?>
<h3><?php echo _t('YW_ACLS_LIST').' '.$this->ComposeLinkToPage($this->GetPageTag()) ?></h3><!-- Access Control Lists for-->

<?php echo  $this->FormOpen('acls', '', 'post', 'form-horizontal') ?>
<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_READ');?> : </label>
  <div class="controls col-sm-9">
    <textarea class="form-control" name="read_acl" rows="3" cols="20"><?php echo  $readACL["list"] ?></textarea>
  </div>
</div>
<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_WRITE');?> : </label>
  <div class="controls col-sm-9">
    <textarea class="form-control" name="write_acl" rows="3" cols="20"><?php echo  $writeACL["list"] ?></textarea>
  </div>
</div>

<?php if (!$this->page['comment_on']) : ?>
<input type="hidden" name="comment_acl" value="<?php echo  $commentACL["list"] ?>">
<?php endif; ?>

<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_CHANGE_OWNER');?> : </label>
  <div class="controls col-sm-9">
    <select class="form-control" name="newowner">
      <option value=""><?php echo _t('YW_CHANGE_NOTHING');?></option><!-- Don't change-->
      <option value="">&nbsp;</option>
<?php
if ($users = $this->LoadUsers()) {
    foreach ($users as $user) {
        echo "<option value=\"",htmlspecialchars($user["name"], ENT_COMPAT, YW_CHARSET),"\">",$user["name"],"</option>\n";
    }
}
?>
    </select>
  </div>
</div>

<div class="form-actions form-group">
    <div class="col-sm-9 col-sm-offset-3">
      <input type="submit" value="<?php echo _t('SAVE') ?>" class="btn btn-success" accesskey="s" /><!-- Store ACLs-->
      <input type="button" value="<?php echo _t('YW_CANCEL') ?>" onclick="history.back();" class="btn btn-danger btn-xs" /><!-- Cancel -->
    </div>
</div>

<?php
echo $this->FormClose();
    }
} else {
    echo '<div class="alert alert-danger">'._t('YW_CANNOT_CHANGE_ACLS').'</div>';
}

?>
</div>
<?php

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();
