<?php
/*
*/
use YesWiki\Security\Controller\SecurityController;
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

if ($this->HasAccess('write') && $this->HasAccess('read')) {
    // Edition
    if (!isset($_POST['submit']) || (isset($_POST['submit']) && $_POST['submit'] != 'Sauver')) {
        if ($this->config['use_hashcash']) {
            require_once 'tools/security/secret/wp-hashcash.lib';
            if (!file_exists(HASHCASH_SECRET_FILE)) {
                $handle = fopen(HASHCASH_SECRET_FILE, 'w');
                fclose($handle);
            }
            // UPDATE RANDOM SECRET
            $curr = @file_get_contents(HASHCASH_SECRET_FILE);
            if (empty($curr) || (time() - @filemtime(HASHCASH_SECRET_FILE)) > HASHCASH_REFRESH) {
                if (is_writable(HASHCASH_SECRET_FILE)) {
                    //update our secret
                    $fp = fopen(HASHCASH_SECRET_FILE, 'w');
                    fwrite($fp, rand(21474836, 2126008810));
                    fclose($fp);
                }
            }

            $ChampsHashcash =
            '<script type="text/javascript" src="'.$this->getBaseUrl().'/tools/security/wp-hashcash-js.php?siteurl='.urlencode($this->getBaseUrl().'/').'"></script><span id="hashcash-text" style="display:none" class="pull-right">'._t('HASHCASH_ANTISPAM_ACTIVATED').'</span>';

            $plugin_output_new = preg_replace(
                '/\<hr class=\"hr_clear\" \/\>/',
                $ChampsHashcash.'<hr class="hr_clear" />',
                $plugin_output_new
            );
        }
        $this->services->get(SecurityController::class)->renderCaptcha($plugin_output_new);
    }
}
