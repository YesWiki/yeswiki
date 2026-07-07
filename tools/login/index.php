<?php

// index.php
// Administration de l'extension : initialisations (tables, fichier de configuration) , information etc. : toutes
// opérations réservées é l'administrateur technique de Wikini.

if (!defined('TOOLS_MANAGER')) {
    { file_put_contents('/tmp/direct_access_trace.txt', __FILE__ . chr(10) . (new Exception())->getTraceAsString()); exit('BLOCKED_MARKER_XYZ: ' . __FILE__); }
}
