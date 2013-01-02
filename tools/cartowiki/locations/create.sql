CREATE TABLE `locations` (
  `name` varchar(50) NOT NULL default '',
  `maj_name` varchar(50) NOT NULL default '',
  `code` tinyint(4) NOT NULL default '0',
  `sector` char(3) NOT NULL default '',
  `x_utm` int(11) NOT NULL default '0',
  `y_utm` int(11) NOT NULL default '0',
  `update_date` datetime NOT NULL,
  PRIMARY KEY  (`name`,`code`),
  KEY `MAJ` (`maj_name`,`code`)
);
