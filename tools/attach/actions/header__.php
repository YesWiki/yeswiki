<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

$remplacement = '<link href="tools/attach/presentation/styles/fileuploader.css" rel="stylesheet" type="text/css">';

$plugin_output_new = preg_replace ('/<\/head>/', $remplacement."\n".'</head>', $plugin_output_new);

?>
