<?php
/**
 * Handler 'css' to render a page with a CSS header.
 *
 * @category YesWiki
 *
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 *
 * @see     https://yeswiki.net
 */
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

header('Content-Type: text/css');
if ($this->HasAccess('read') && $this->page && isset($this->page['body'])) {
    echo $this->page['body'];
}
