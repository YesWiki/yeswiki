<?php

// {{filemanager}} page handler (ticket 17, relocated from tools/attach/handlers/page/filemanager.php).
// Manages files linked via the {{attach}} action. Requires actions/attach.php.

use YesWiki\Content\Attach;

ob_start();
?>
<div class="page">
    <?php
    if ($this->UserIsOwner() || $this->UserIsAdmin()) {
        $att = new Attach($this);
        $att->doFileManager();
        unset($att);
    } else {
        echo $this->Format('//' . _t('FILEMANAGER_ACTION_NEED_ACCESS') . '//');
    }
?>
</div>
<?php
$output = ob_get_contents();
ob_end_clean();
echo $this->Header() . $output . $this->Footer(); ?>
