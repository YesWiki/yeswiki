<?php

/** What the field specs arrange in the wiki under test: `create <id> <label> <fields-json>` adds a form. */

use YesWiki\Content\Service\FormManager;
use YesWiki\Core\YesWikiLoader;

require_once dirname(__DIR__, 3) . '/src/YesWikiLoader.php';

$forms = YesWikiLoader::getWiki(true)->services->get(FormManager::class);
[$command, $id, $label, $fields] = array_pad(array_slice($argv, 1), 4, '');

if ($command !== 'create' || $id === '' || !is_array(json_decode($fields, true))) {
    fwrite(STDERR, "usage: forms-wiki.php create <id> <label> <fields-json>\n");
    exit(1);
}

$forms->create(['id' => $id, 'label' => $label, 'template' => $fields, 'condition' => '']);
