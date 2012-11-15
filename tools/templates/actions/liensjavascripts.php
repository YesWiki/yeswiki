<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
<<<<<<< HEAD
//javascripts
$wikini_javascripts = '<script type="text/javascript" src="tools/templates/libs/jquery.tools.1.2.7-jquery.1.7.2.min.js"></script>';
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}
=======
>>>>>>> mrflos/bachibouzouk

echo $this->Format('{{linkjavascript}}');

?>
