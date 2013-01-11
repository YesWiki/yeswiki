<?php
	$this->config = array(
		'db_host' => FERME_DB_HOST,
		'db_name' => FERME_DB_NAME,
		'db_user' => FERME_DB_USER,
		'db_password' => FERME_DB_PASSWORD,
		'ferme_path' => FERME_PATH,
		'base_url' => FERME_BASE_URL,
		'source_path' => FERME_SOURCE_PATH,
		'template' =>FERME_TEMPLATE,
		'newDir' => array(
			'cache',
			'files',
			'themes',
		),
		'copyList' => array(
			'wakka.php',
			'tools.php',
			'index.php',
		),
		'symList' => array(
			'actions',
			'formatters',
			'handlers',
			'includes',
			'setup',
			'tools',
			'interwiki.conf',
			'robots.txt',
			'wakka.basic.css',
			'wakka.css',
		),
		'themes' => $GLOBALS['themesyeswiki']
	);
?>
