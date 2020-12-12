<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->parameter['msg'] = "this variable is from <em>__hello.php</em>";

$msg2 = "the ugly way of passing then getting variables still works for backwards compatibility but please don't do it :)";