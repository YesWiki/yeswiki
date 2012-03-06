<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}
//javascripts
$wikini_javascripts = '<script type="text/javascript" src="tools/templates/libs/jquery-1.7.1.min.js"></script>
<script type="text/javascript" src="tools/templates/libs/jquery.tools.min.js"></script>';
/*'<script src="http://cdn.jquerytools.org/1.2.6/full/jquery.tools.min.js"></script>'."\n".
'<script>window.jQuery || document.write(\'<script src="tools/templates/libs/jquery.tools.1.2.6-jquery.1.6.4.min.js">\/script>\') </script>'."\n";
*/
if (is_dir('themes/'.$this->config['favorite_theme'].'/javascripts')) {
	$repertoire = 'themes/'.$this->config['favorite_theme'].'/javascripts';
} else {
	$repertoire = 'tools/templates/themes/'.$this->config['favorite_theme'].'/javascripts';
}

$dir = opendir($repertoire);
while (false !== ($file = readdir($dir))) {
  if (substr($file, -3, 3)=='.js') $scripts[] = '<script defer type="text/javascript" src="'.$repertoire.'/'.$file.'"></script>';
}
asort($scripts);
foreach ($scripts as $key => $val) {
    $wikini_javascripts .= "\n$val";
}
closedir($dir);

echo $wikini_javascripts;
?>
