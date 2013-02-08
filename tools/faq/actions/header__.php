<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

	$plugin_output_new=preg_replace ('/<\/head>/',
	'<link rel="stylesheet" type="text/css" href="tools/faq/presentation/faq.css" />
	</head>   
	',
	$plugin_output_new);

?>	
