<?php

// {{attach}} action (ticket 17, relocated from tools/attach/actions/attach.php).
// `file=`/`attachfile=` is a FileManager file-entry tag (see src/Attach.php's
// CheckParams()); see docs/actions/attach.yaml for the full argument list.

use YesWiki\Core\Attach;

$att = new Attach($this);
$att->doAttach();
unset($att);
