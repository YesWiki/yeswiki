<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
//attributs du body
$toastDuration = !empty($this->config['toast_duration']) ? $this->config['toast_duration'] : '3000';
$toastClass = !empty($this->config['toast_class']) ? $this->config['toast_class'] : 'alert alert-secondary-1';
$body_attr = ($message = $this->GetMessage()) ? "onload=\"toastMessage('" . addslashes($message) . "', " . $toastDuration . ", '" . $toastClass . "');\" " : '';
echo $body_attr;
