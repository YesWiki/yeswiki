<?php

use YesWiki\Security\Controller\SecurityController;

// Vérification de sécurité
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

ob_start();
?>
<div class="page">
<?php
if ($this->page && ($this->UserIsOwner() || $this->UserIsAdmin())) {
    if ($_POST) {
        // store lists
        $this->SaveAcl($this->GetPageTag(), 'read', $_POST['read_acl']);
        $this->SaveAcl($this->GetPageTag(), 'write', $_POST['write_acl']);
        $this->SaveAcl($this->GetPageTag(), 'comment', ($this->page['comment_on'] ? '' : $_POST['comment_acl']));
        $message = _t('YW_ACLS_UPDATED');

        // change owner?
        if ($newowner = $_POST['newowner']) {
            $this->SetPageOwner($this->GetPageTag(), $newowner);
            $message .= _t('YW_NEW_OWNER') . $newowner;
        }

        // redirect back to page
        $this->SetMessage($message . ' !');
        $this->Redirect($this->Href());
    } else {
        // load acls
        $readACL = $this->LoadAcl($this->GetPageTag(), 'read');
        $writeACL = $this->LoadAcl($this->GetPageTag(), 'write');
        $commentACL = $this->LoadAcl($this->GetPageTag(), 'comment');

        // show form?>
<h3><?php echo _t('YW_ACLS_LIST') . ' ' . $this->ComposeLinkToPage($this->GetPageTag()); ?></h3><!-- Access Control Lists for-->

<?php echo $this->FormOpen('acls', '', 'post', 'form-horizontal'); ?>
<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_READ'); ?> : </label>
  <div class="controls col-sm-9">
    <textarea class="form-control" name="read_acl" rows="3" cols="20"
      <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
        } ?>
      ><?php echo $readACL['list']; ?></textarea>
  </div>
</div>
<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_WRITE'); ?> : </label>
  <div class="controls col-sm-9">
    <textarea class="form-control" name="write_acl" rows="3" cols="20"
      <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
        } ?>
      ><?php echo $writeACL['list']; ?></textarea>
  </div>
</div>

<?php if (!$this->page['comment_on']) { ?>
<input type="hidden" name="comment_acl" value="<?php echo $commentACL['list']; ?>">
<?php } ?>

<div class="form-group">
  <label class="control-label col-sm-3"><?php echo _t('YW_CHANGE_OWNER'); ?> : </label>
  <div class="controls col-sm-9">
    <select class="form-control" name="newowner"
      <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
        } ?>
      >
      <option value=""><?php echo _t('YW_CHANGE_NOTHING'); ?></option><!-- Don't change-->
      <option value="">&nbsp;</option>
<?php
if ($users = $this->LoadUsers()) {
            foreach ($users as $user) {
                echo '<option value="',htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET),'">',$user['name'],"</option>\n";
            }
        } ?>
    </select>
  </div>
</div>

<div class="form-actions form-group">
    <div class="col-sm-9 col-sm-offset-3">
      <input type="submit" value="<?php echo _t('SAVE'); ?>" class="btn btn-primary" accesskey="s" 
      <?php if ($this->services->get(SecurityController::class)->isWikiHibernated()) {
            echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
        } ?>
      /><!-- Store ACLs-->
      <input type="button" value="<?php echo _t('YW_CANCEL'); ?>" onclick="if(history.length>1){history.back();}else{location.href='<?php echo $this->Href(); ?>';}" class="btn btn-default btn-xs" /><!-- Cancel -->
    </div>
</div>

<?php
echo $this->FormClose();
    }
} else {
    echo '<div class="alert alert-danger">' . _t('YW_CANNOT_CHANGE_ACLS') . '</div>';
}

?>
</div>
<?php

$content = ob_get_clean();
echo $this->Header();
echo $content;
echo $this->Footer();
