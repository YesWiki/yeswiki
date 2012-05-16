<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

	$remplacement ='
	<link rel="stylesheet" href="tools/perudo/presentation/styles/perudo.css" type="text/css" media="screen" />
	';

	$plugin_output_new=preg_replace ('/<\/head>/', $remplacement."\n".'</head>', $plugin_output_new);

?>
