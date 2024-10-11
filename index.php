<?php
/**
 * Yeswiki start file.
 *
 * Instantiates the main YesWiki class, loads the extensions,
 * and runs the current page
 *
 * @category Wiki
 *
 * @license  GNU/GPL version 3
 *
 * @see     https://yeswiki.net
 */

use YesWiki\Core\YesWikiLoader;

require_once 'includes/YesWikiLoader.php';
$wiki = YesWikiLoader::getWiki();
$wiki->Run($wiki->tag, $wiki->method);
