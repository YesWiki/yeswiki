CREATE TABLE IF NOT EXISTS `{{prefix}}acls` (
  `page_tag` varchar(191) NOT NULL,
  `privilege` varchar(20) NOT NULL,
  `list` text NOT NULL,
  PRIMARY KEY (`page_tag`,`privilege`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `from_tag` char(191) NOT NULL,
  `to_tag` char(191) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `from_tag` (`from_tag`,`to_tag`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}nature` (
  `bn_id_nature` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bn_label_nature` varchar(255) DEFAULT NULL,
  `bn_description` text  DEFAULT NULL,
  `bn_condition` text DEFAULT NULL,
  `bn_sem_context` text DEFAULT NULL,
  `bn_sem_type` varchar(255) DEFAULT NULL,
  `bn_sem_use_template` tinyint(1) NOT NULL DEFAULT 1,
  `bn_template` text NOT NULL,
  `bn_ce_i18n` varchar(5) NOT NULL,
  PRIMARY KEY (`bn_id_nature`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}pages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tag` varchar(191) NOT NULL,
  `time` datetime NOT NULL,
  `body` longtext NOT NULL,
  `body_r` text NOT NULL,
  `owner` varchar(191) NOT NULL,
  `user` varchar(191) NOT NULL,
  `latest` enum('Y','N') NOT NULL DEFAULT 'N',
  `handler` varchar(30) NOT NULL DEFAULT 'page',
  `comment_on` varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_tag` (`tag`),
  KEY `idx_time` (`time`),
  KEY `idx_latest` (`latest`),
  KEY `idx_comment_on` (`comment_on`),
  FULLTEXT KEY `tag` (`tag`,`body`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}referrers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_tag` varchar(191) NOT NULL,
  `referrer` text NOT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_page_tag` (`page_tag`),
  KEY `idx_time` (`time`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}triples` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `resource` varchar(255) NOT NULL,
  `property` varchar(255) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resource` (`resource`),
  KEY `property` (`property`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `{{prefix}}users` (
  `name` varchar(80) NOT NULL,
  `password` varchar(32) NOT NULL,
  `email` varchar(191) NOT NULL,
  `motto` text NOT NULL,
  `revisioncount` int(10) unsigned NOT NULL DEFAULT '20',
  `changescount` int(10) unsigned NOT NULL DEFAULT '50',
  `doubleclickedit` enum('Y','N') NOT NULL DEFAULT 'Y',
  `signuptime` datetime NOT NULL,
  `show_comments` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`name`),
  KEY `idx_name` (`name`),
  KEY `idx_signuptime` (`signuptime`)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci ENGINE=InnoDB;

# Creation of admins group and admin user
INSERT INTO `{{prefix}}triples` (`id`, `resource`, `property`, `value`) VALUES
(1, 'ThisWikiGroup:admins', 'http://www.wikini.net/_vocabulary/acls', '{{WikiName}}');

INSERT INTO `{{prefix}}users` (`name`, `password`, `email`, `motto`, `revisioncount`, `changescount`, `doubleclickedit`, `signuptime`, `show_comments`) VALUES
('{{WikiName}}', md5('{{password}}'), '{{email}}', '', 20, 50, 'Y',  now(), 'N');
