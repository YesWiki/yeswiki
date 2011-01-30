<?php
# ***** BEGIN LICENSE BLOCK *****
# This file is part of DotClear.
# Copyright (c) 2004 Geoffrey Bachelet and contributors. All rights
# reserved.
#
# DotClear is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 2 of the License, or
# (at your option) any later version.
# 
# DotClear is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with DotClear; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
#
# ***** END LICENSE BLOCK *****
#
# Contributors :
# - Olivier Meunier
#

require dirname(__FILE__).'/lib.php';

$err = '';
$tool_url = 'tools.php?p=packager';

# Liste des thèmes
$themes_root = dirname(__FILE__).'/../../../themes';
$themes = new plugins($themes_root,'theme');
$themes->getPlugins(false);
$themes_list = $themes->getPluginsList();

$plugins_root = dirname(__FILE__).'/..';
$plugins = new plugins($plugins_root);
$plugins->getPlugins(false);
$plugins_list = $plugins->getPLuginsList();

# Préparation des tableaux pour les combo
$t_list = $p_list = array();

foreach($themes_list as $k => $v) {
	$t_list[$v['label'].' - '.__('version').' '.$v['version']] = $k;
}
foreach($plugins_list as $k => $v) {
	$p_list[$v['label'].' - '.__('version').' '.$v['version']] = $k;
}


# Téléchargement ou sauvegarde d'un plugin
if (!empty($_POST['p_plugin']))
{
	$fname = 'plugin-'.$_POST['p_plugin'];
	if (!empty($plugins_list[$_POST['p_plugin']]['version'])) {
		$fname .= '-'.$plugins_list[$_POST['p_plugin']]['version'];
	}
	$fname .= '.pkg.gz';
	
	dcPackager::packIt(
			$_POST['p_plugin'],
			$plugins_root,
			$fname,
			(!empty($_POST['p_save']) && is_writable(DC_SHARE_DIR)),
			$tool_url.'&p_ok=1',
			__('An error occured while creating the plugin.'),
			$err);
}


# Téléchargement ou sauvegarde d'un plugin
if (!empty($_POST['p_theme']))
{
	$fname = 'theme-'.$_POST['p_theme'];
	if (!empty($themes_list[$_POST['p_theme']]['version'])) {
		$fname .= '-'.$themes_list[$_POST['p_theme']]['version'];
	}
	$fname .= '.pkg.gz';
	
	dcPackager::packIt(
			$_POST['p_theme'],
			$themes_root,
			$fname,
			(!empty($_POST['p_save']) && is_writable(DC_SHARE_DIR)),
			$tool_url.'&t_ok=1',
			__('An error occured while creating the theme.'),
			$err);
}


/* Affichage
-------------------------------------------------------- */
buffer::str('<h2>'.__('Themes and plugins packing').'</h2>');

if ($err != '') {
	buffer::str(
	'<div class="erreur"><p><strong>'.__('Error(s)').' :</strong></p>'.
	$err.
	'</div>'
	);
}

if (!empty($_GET['p_ok'])) {
	buffer::str('<p class="message">'.__('Plugin saved.').'</p>');
}

if (!empty($_GET['t_ok'])) {
	buffer::str('<p class="message">'.__('Theme saved.').'</p>');
}

buffer::str(
'<form action="'.$tool_url.'" method="post">'.
'<fieldset class="clear"><legend>'.__('Pack a plugin').'</legend>'.
'<p class="field"><label class="float" for="p_plugin">'.__('Plugin name').' :</label>'.
form::combo('p_plugin',$p_list).'</p>'.
'<p><input class="submit" type="submit" name="p_dl" value="'.__('Download this plugin').'" />'
);

if (is_writable(DC_SHARE_DIR)) {
	buffer::str(
	' <input class="submit" type="submit" name="p_save" value="'.__('Save this plugin in share folder').'" /></p>'
	);
}

buffer::str(
'</p>'.
'</fieldset></form>'
);


buffer::str(
'<form action="'.$tool_url.'" method="post">'.
'<fieldset class="clear"><legend>'.__('Pack a theme').'</legend>'.
'<p class="field"><label class="float" for="p_theme">'.__('Theme name').' :</label>'.
form::combo('p_theme',$t_list).'</p>'.
'<p><input class="submit" type="submit" name="p_dl" value="'.__('Download this theme').'" />'
);

if (is_writable(DC_SHARE_DIR)) {
	buffer::str(
	' <input class="submit" type="submit" name="p_save" value="'.__('Save this theme in share folder').'" /></p>'
	);
}

buffer::str(
'</p>'.
'</fieldset></form>'
);

?>
