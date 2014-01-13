CREATE TABLE IF NOT EXISTS `_t('BAZ_PREFIXEnature')` (
  `bn_id_nature` int(10) unsigned NOT NULL DEFAULT '0',
  `bn_label_nature` varchar(255) DEFAULT NULL,
  `bn_description` text,
  `bn_condition` text,
  `bn_ce_id_menu` int(3) unsigned NOT NULL DEFAULT '0',
  `bn_commentaire` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bn_appropriation` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `bn_image_titre` varchar(255) NOT NULL DEFAULT '',
  `bn_image_logo` varchar(255) NOT NULL DEFAULT '',
  `bn_couleur_calendrier` varchar(255) NOT NULL DEFAULT '',
  `bn_picto_calendrier` varchar(255) NOT NULL DEFAULT '',
  `bn_template` text NOT NULL,
  `bn_ce_i18n` varchar(5) NOT NULL DEFAULT '',
  `bn_type_fiche` varchar(255) NOT NULL,
  `bn_label_class` varchar(255) NOT NULL,
  PRIMARY KEY (`bn_id_nature`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
