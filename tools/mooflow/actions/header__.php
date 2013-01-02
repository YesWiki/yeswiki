<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

define ('CHEMIN', 'tools'.DIRECTORY_SEPARATOR.'mooflow'.DIRECTORY_SEPARATOR.'actions'.DIRECTORY_SEPARATOR);

$header = '<script type="text/javascript" src="'.CHEMIN.'js/mootools-beta-1.2b2.js"></script>
<script type="text/javascript" src="'.CHEMIN.'js/MooFlow.js"></script>
</head>';

if ($this->GetMethod() == "show") {
	$plugin_output_new=preg_replace ('/<\/head>/', $header,	$plugin_output_new);
}