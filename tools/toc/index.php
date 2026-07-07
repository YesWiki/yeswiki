<?php

// index.php
if (!defined('TOOLS_MANAGER')) {
    { file_put_contents('/tmp/direct_access_trace.txt', __FILE__ . chr(10) . (new Exception())->getTraceAsString()); exit('BLOCKED_MARKER_XYZ: ' . __FILE__); }
}
