<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$msg .= ', then display in the <em>hello__.php</em>';

$plugin_output_new = str_replace('MY_MESSAGE', $msg, $plugin_output_new);
$plugin_output_new = str_replace('CONCLUSION', ' => the new actions/handlers/formatters are compatible ' .
    'with the old ones', $plugin_output_new);
