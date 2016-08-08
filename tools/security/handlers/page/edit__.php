<?php
/*
*/
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

            if (substr($this->config['base_url'], 0, 4) != 'http') { // Wakka.config mal configure
                $base_url = 'http://'.$_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] != 80 ? ':'.$_SERVER['SERVER_PORT'] : '').$_SERVER['REQUEST_URI'].(preg_match('/'.preg_quote('wakka.php').'$/', $_SERVER['REQUEST_URI']) ? '?wiki=' : '');
                $a = parse_url($base_url);
            } else {
                $a = parse_url($this->config['base_url']);
            }
            $siteurl = $a['scheme'].'://'.$a['host'].str_replace('\\', '/', dirname($a['path']));
            if (mb_substr($siteurl, -1) == '/') {
                $slash = '';
            } else {
                $slash = '/';
            }

            $ChampsHashcash =
             '<script type="text/javascript" src="'.$siteurl.$slash.'tools/security/wp-hashcash-js.php?siteurl='.urlencode($siteurl.$slash).'"></script><span id="hashcash-text" style="display:none" class="pull-right">'._t('HASHCASH_ANTISPAM_ACTIVATED').'</span>';

            $plugin_output_new = preg_replace(
                '/\<hr class=\"hr_clear\" \/\>/',
                $ChampsHashcash.'<hr class="hr_clear" />',
                $plugin_output_new
            );
        }
    }
}
