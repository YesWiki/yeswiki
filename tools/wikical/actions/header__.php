<?php 
$plugin_output_new=preg_replace ('/<link rel="stylesheet" type="text\/css"/',
	'
	<link rel="stylesheet" type="text/css" href="tools/wikical/presentation/styles/cal.css" media="screen" />	
	<link rel="stylesheet" type="text/css"',
	$plugin_output_new, $limit=1);




?>