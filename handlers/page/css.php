<?php
/**
 * Handler 'css' to render a page with a CSS header
 *
 * @category YesWiki
 * @package  YesWiki
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 * @link     https://yeswiki.net
 */

if (!defined("WIKINI_VERSION"))
{
    die ("acc&egrave;s direct interdit");
}

header('Content-Type: text/css');
echo $this->page['body'];
