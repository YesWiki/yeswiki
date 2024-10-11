<?php

use YesWiki\Security\Controller\SecurityController;
use YesWiki\Security\Service\HashCashService;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    // Edition
    if (!isset($_POST['submit']) || (isset($_POST['submit']) && $_POST['submit'] != 'Sauver')) {
        if ($this->config['use_hashcash']) {
            $hashCash = $this->services->get(HashCashService::class);
            $hashCashCode = $hashCash->getJavascriptCode();
            $plugin_output_new = preg_replace(
                '/\<hr class=\"hr_clear\" \/\>/',
                $hashCashCode . '<hr class="hr_clear" />',
                $plugin_output_new
            );
        }
        $this->services->get(SecurityController::class)->renderCaptcha($plugin_output_new);
    }
}
