<?php

// {{filemanager}} action (ticket 17, relocated from tools/attach/actions/filemanager.php).
// Manages files linked via the {{attach}} action. Requires actions/attach.php.

use YesWiki\Content\Attach;

if ($this->HasAccess('write')) {
    $att = new Attach($this);
    $att->doFileManagerAction();
    unset($att);
} else {
    echo '<div class="yw-alert yw-alert--danger">' . _t('ATTACH_NO_RIGHTS_TO_ACCESS_FILEMANAGER') . '.</div>' . "\n";
}
