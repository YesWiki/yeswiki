<?php
$configFileContent = "<?php
// wakka.config.php cr&eacute;&eacute;e Tue Oct  4 09:06:58 2011
// ne changez pas la wikini_version manuellement!

\$wakkaConfig = array (
	'wakka_version' => '0.1.1',
	'wikini_version' => '0.5.0',
	'debug' => 'no',
	'mysql_host' => '".$this->config['db_host']."',
	'mysql_database' => '".$this->config['db_name']."',
	'mysql_user' => '".$this->config['db_user']."',
	'mysql_password' => '".$this->config['db_password']."',
	'table_prefix' => '$table_prefix',
	'root_page' => 'Accueil',
	'wakka_name' => '$wikiName',
	'base_url' => '$wiki_url',
	'rewrite_mode' => '0',
	'meta_keywords' => '',
	'meta_description' => '',
	'action_path' => 'actions',
	'handler_path' => 'handlers',
	'header_action' => 'header',
	'footer_action' => 'footer',
	'navigation_links' => 'DerniersChangements :: DerniersCommentaires :: ParametresUtilisateur',
	'referrers_purge_time' => 24,
	'pages_purge_time' => 90,
	'default_write_acl' => '*',
	'default_read_acl' => '*',
	'default_comment_acl' => '*',
	'preview_before_save' => '0',
	'allow_raw_html' => '1',
	'favorite_theme' => '".$this->config['themes'][$_POST['theme']]['theme']."',
	'favorite_style' => '".$this->config['themes'][$_POST['theme']]['style']."',
	'favorite_squelette' => '".$this->config['themes'][$_POST['theme']]['squelette']."',
);
?>";/**/
?>
