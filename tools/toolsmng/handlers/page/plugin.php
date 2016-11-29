<?php
/*
listplugins.php

Copyright 2003  David DELON
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$plugins_root = dirname(__FILE__).'/../../../';
$plugins = new plugins($plugins_root);
$plugins->getPlugins(true);
$plugins_list = $plugins->getPluginsList();


# Tri des plugins par leur nom
uasort($plugins_list,create_function('$a,$b','return strcmp($a["label"],$b["label"]);'));


echo $this->Header();
?>
<div class="page">
<?php

echo "Liste des plugins installés :";
echo '<dl>';
foreach ($plugins_list as $k => $v)
{
	echo  '<dt>'.$v['label'].' - '.$k.'</dt>';
	echo  '<dd>'.$v['desc'].' <br />';
	echo  'par '.$v['author'].' - '.'version'.' '.$v['version'].' <br />';
	echo('</dd>');
}
echo('</dl>');

?>
</div>
<?php 
	echo $this->Footer(); 
?>