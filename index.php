<?php

/**
 * Yeswiki start file.
 *
 * Instantiates the YesWiki runtime, loads the extensions,
 * and runs the current page
 *
 * @category Wiki
 *
 * @license GNU/GPL version 3
 *
 * @see https://yeswiki.net
 */

use YesWiki\Core\YesWikiLoader;
use YesWiki\Kernel\Service\AssetPublisher;

// source/instance dirs separation + instance data folders provisioning
require_once __DIR__ . '/src/bootstrap_paths.php';

// static asset requests falling through the rewrite rules (missing published asset, or a
// source asset absent from a farm instance's docroot) are served without booting the wiki
require_once __DIR__ . '/src/Kernel/Service/AssetPublisher.php';
AssetPublisher::interceptAssetRequest();

require_once __DIR__ . '/src/YesWikiLoader.php';
$wiki = YesWikiLoader::getWiki();
$wiki->run();
