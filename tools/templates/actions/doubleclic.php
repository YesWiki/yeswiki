<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
$page = $this->GetParameter('page');
$isIframe = $this->GetParameter('iframe') && (!isset($_GET['iframelinks']) or $_GET['iframelinks'] != '0');
if ($this->GetMethod() == 'show' && $this->HasAccess('write', $page)) {
    $method = $isIframe ? 'editiframe' : 'edit';
    // javascript du double clic (on peut passer en parametre une page wiki au editer en doublecliquant)
    if (!empty($page)) {
        echo 'ondblclick="document.location=\'' . $this->href($method, $page) . '\';" ';
    } else {
        echo 'ondblclick="document.location=\'' . $this->href($method) . '\';" ';
    }
}
