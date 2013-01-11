<?php
include_once("tools/clickheat/config.php");

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}


if (version_compare(phpversion(), '5.0') < 0) {
    eval('
    if (!function_exists("clone")) {
        function clone($object) {
                return $object;
        }
    }
    ');
}

$plugin_output_new=preg_replace ('/<head>/',
	'<head>
	<script type="text/javascript" src="'.$clickheatsource.'"></script>
	<script type="text/javascript"> <!-- 
	    clickHeatSite = \''.$clickheatsite.'\'; 
	    clickHeatGroup = \''.$clickheatgroup.'\'; 
	    clickHeatServer = \''.$clickheatserver.'\';
	    if (typeof initClickHeat == \'string\' && eval(\'typeof \' + initClickHeat) == \'function\') {initClickHeat();}; 
	//--> </script>',
	$plugin_output_new);
?>
