<?php

namespace YesWiki\Security\Service;

use YesWiki\Wiki;

class HashCashService
{
    protected $wiki;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function getJavascriptCode($formId = 'ACEditor')
    {
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

        return '<script type="text/javascript" src="' . $this->wiki->getBaseUrl() . '/tools/security/wp-hashcash-js.php?formid=' . $formId . '&siteurl=' . urlencode($this->wiki->getBaseUrl() . '/') . '"></script><span id="hashcash-text" style="display:none" class="pull-right">' . _t('HASHCASH_ANTISPAM_ACTIVATED') . '</span>';
    }
}
