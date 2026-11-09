<?php

use YesWiki\AutoUpdate\Service\MigrationService;
use YesWiki\Core\YesWikiLoader;

unset($_POST['config']);

require_once 'includes/YesWikiLoader.php';
$GLOBALS['wiki'] = $wiki = YesWikiLoader::getWiki();
$messages = $wiki->services->get(MigrationService::class)->run();

echo $wiki->render('@autoupdate/update-result.twig', [
    'messages' => $messages->toArray(),
]);

echo "<div class=\"form-actions\">\n<a class=\"btn btn-lg btn-primary\" href=\"", $wiki->config['base_url'] . $wiki->config['root_page'], '">' . _t('GO_TO_YOUR_NEW_YESWIKI_WEBSITE') . "</a>\n</div>\n";
