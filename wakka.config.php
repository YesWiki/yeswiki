<?php
// wakka.config.php cr&eacute;&eacute;e Sun Sep 26 17:46:14 2010
// ne changez pas la wikini_version manuellement!

$wakkaConfig = array (
  'wakka_version' => '0.1.1',
  'wikini_version' => '0.5.0',
  'debug' => 'no',
  'mysql_host' => 'localhost',
  'mysql_database' => 'yeswiki',
  'mysql_user' => 'root',
  'mysql_password' => 'fs1980',
  'table_prefix' => 'yeswiki_',
  'root_page' => 'AccueiL',
  'wakka_name' => 'YesWiki de développement',
  'base_url' => 'http://localhost/wakka.php?wiki=',
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
);
?>