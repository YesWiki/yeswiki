<?php
/**
 * Yeswiki start file
 *
 * Instantiates the main YesWiki class, loads the extensions,
 * and runs the current page
 *
 * @category Wiki
 * @package  YesWiki
 * @license  GNU/GPL version 3
 * @link     https://yeswiki.net
 */

use YesWiki\Core\YesWikiLoader;

require_once 'includes/YesWikiLoader.php';

$wiki = YesWikiLoader::getWiki();
$wiki->Run($wiki->tag, $wiki->method);
