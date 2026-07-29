<?php

namespace YesWiki\Content\Handler;

use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiHandler;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Performable\RegisteredHandler;
use YesWiki\Kernel\Service\FlashMessageService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\LinkRenderer;

/**
 * `/PageName/acls` -- converted from the procedural handlers/page/acls.php by ticket 06.
 */
class AclsHandler extends YesWikiHandler implements RegisteredHandler
{
    public static function performableName(): string
    {
        return 'acls';
    }

    public function run(): string
    {
        ob_start();
        try {
            $this->emit();
        } catch (\Throwable $t) {
            // handlers commonly end in exit()/redirect, which throw; keep what was already
            // printed and close the buffer either way (see ticket 06)
            $this->output .= (string)ob_get_clean();

            throw $t;
        }

        return (string)ob_get_clean();
    }

    private function emit(): void
    {
        ob_start();
        ?>
        <div class="page">
          <?php
          if ($this->wiki->page && ($this->getService(AclService::class)->isOwner() || $this->getService(AclService::class)->isAdmin())) {
              if ($_POST) {
                  // store lists
                  $this->getService(AclService::class)->save($this->wiki->GetPageTag(), 'read', $_POST['read_acl']);
                  $this->getService(AclService::class)->save($this->wiki->GetPageTag(), 'write', $_POST['write_acl']);
                  $this->getService(AclService::class)->save($this->wiki->GetPageTag(), 'comment', $this->wiki->page['comment_on'] ? '' : $_POST['comment_acl']);
                  $message = _t('YW_ACLS_UPDATED');

                  // change owner?
                  if ($newowner = $_POST['newowner']) {
                      $this->getService(PageManager::class)->setOwner($this->wiki->GetPageTag(), $newowner);
                      $message .= _t('YW_NEW_OWNER') . $newowner;
                  }

                  // redirect back to page
                  $this->getService(FlashMessageService::class)->setMessage($message . ' !');
                  $this->wiki->Redirect($this->getService(UrlFormatter::class)->href());
              } else {
                  // load acls
                  $readACL = $this->getService(AclService::class)->load($this->wiki->GetPageTag(), 'read');
                  $writeACL = $this->getService(AclService::class)->load($this->wiki->GetPageTag(), 'write');
                  $commentACL = $this->getService(AclService::class)->load($this->wiki->GetPageTag(), 'comment');

                  // show form?>
              <h3><?php echo _t('YW_ACLS_LIST') . ' ' . $this->getService(LinkRenderer::class)->linkToPage($this->wiki->GetPageTag()); ?></h3><!-- Access Control Lists for-->

              <?php echo $this->wiki->FormOpen('acls', '', 'post', 'form-horizontal'); ?>
              <div class="form-group">
                <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_READ'); ?> : </label>
                <div class="controls col-sm-9">
                  <textarea class="form-control" name="read_acl" rows="3" cols="20"
                    <?php if ($this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                        echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
                    } ?>><?php echo $readACL['list'] ?? ''; ?></textarea>
                </div>
              </div>
              <div class="form-group">
                <label class="control-label col-sm-3"><?php echo _t('YW_ACLS_WRITE'); ?> : </label>
                <div class="controls col-sm-9">
                  <textarea class="form-control" name="write_acl" rows="3" cols="20"
                    <?php if ($this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                        echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
                    } ?>><?php echo $writeACL['list'] ?? ''; ?></textarea>
                </div>
              </div>

              <?php if (!$this->wiki->page['comment_on']) { ?>
                <input type="hidden" name="comment_acl" value="<?php echo $commentACL['list'] ?? ''; ?>">
              <?php } ?>

              <div class="form-group">
                <label class="control-label col-sm-3"><?php echo _t('YW_CHANGE_OWNER'); ?> : </label>
                <div class="controls col-sm-9">
                  <select class="form-control" name="newowner"
                    <?php if ($this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                        echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
                    } ?>>
                    <option value=""><?php echo _t('YW_CHANGE_NOTHING'); ?></option><!-- Don't change-->
                    <option value="">&nbsp;</option>
                    <?php
                      if ($users = $this->getService(UserManager::class)->getAll()) {
                          foreach ($users as $user) {
                              echo '<option value="', htmlspecialchars($user['name'], ENT_COMPAT, YW_CHARSET), '">', $user['name'], "</option>\n";
                          }
                      } ?>
                  </select>
                </div>
              </div>

              <div class="form-actions form-group">
                <div class="col-sm-9 col-sm-offset-3">
                  <input type="submit" value="<?php echo _t('SAVE'); ?>" class="btn btn-primary" accesskey="s"
                    <?php if ($this->wiki->services->get(HibernationService::class)->isWikiHibernated()) {
                        echo 'disabled data-toggle="tooltip" data-placement="bottom" title="' . _t('WIKI_IN_HIBERNATION') . '"';
                    } ?> /><!-- Store ACLs-->
                  <input type="button" value="<?php echo _t('YW_CANCEL'); ?>" onclick="if(history.length>1){history.back();}else{location.href='<?php echo $this->getService(UrlFormatter::class)->href(); ?>';}" class="btn btn-default btn-xs" /><!-- Cancel -->
                </div>
              </div>

          <?php
              echo $this->wiki->FormClose();
              }
          } else {
              echo '<div class="alert alert-danger">' . _t('YW_CANNOT_CHANGE_ACLS') . '</div>';
          }

        ?>
        </div>
        <?php

        $content = ob_get_clean();
        echo $this->wiki->Header();
        echo $content;
        echo $this->wiki->Footer();
    }
}
