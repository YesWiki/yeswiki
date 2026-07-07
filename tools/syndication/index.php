<?php

// index.php
// Administration de l'extension : initialisations (tables, fichier de configuration) , information etc. : toutes
// operations reservees a l'administrateur technique de Wikini.

// Verification de securite
if (!defined('TOOLS_MANAGER')) {
    { file_put_contents('/tmp/direct_access_trace.txt', __FILE__ . chr(10) . (new Exception())->getTraceAsString()); exit('BLOCKED_MARKER_XYZ: ' . __FILE__); }
}
