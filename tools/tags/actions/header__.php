<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

	$remplacement ='
	<link rel="stylesheet" href="tools/tags/presentation/styles/jquery-ui.css" type="text/css" media="screen" />
	<link rel="stylesheet" href="tools/tags/presentation/styles/tags.css" type="text/css" media="screen" />
	';

	$plugin_output_new=preg_replace ('/<\/head>/', $remplacement."\n".'</head>', $plugin_output_new);

?>
