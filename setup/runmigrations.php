<?php
use YesWiki\Core\YesWikiLoader;
use YesWiki\AutoUpdate\Service\MigrationService;

unset($_POST['config']);

require_once 'includes/YesWikiLoader.php';
$GLOBALS['wiki'] = $wiki = YesWikiLoader::getWiki();
$migrationService = $wiki->services->get(MigrationService::class);

$messages = $migrationService->run();

echo $wiki->render('@autoupdate/update-result.twig', [
    'messages' => $messages->toArray(),
]);

echo "<div class=\"form-actions\">\n<a class=\"btn btn-lg btn-primary\" href=\"", $wiki->config['base_url'] . $wiki->config['root_page'], '">' . _t('GO_TO_YOUR_NEW_YESWIKI_WEBSITE') . "</a>\n</div>\n";

?>
