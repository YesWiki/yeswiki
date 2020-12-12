<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->parameter['msg'] .= ', then display in the <em>hello__.php</em>';

$this->output = str_replace('MY_MESSAGE', $this->parameter['msg'], $this->output);
$this->output = str_replace('MY_SECOND_MESSAGE', $msg2, $this->output);
// for backwards compatibility, $plugin_output_new is still accessible but depreciated
$plugin_output_new = str_replace('CONCLUSION', ' => you can mix the new actions/handlers/formatters with '
    . 'the old ones, even if we encourage to use the new ones', $plugin_output_new);

