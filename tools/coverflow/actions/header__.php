<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

define ('CHEMIN', 'tools'.DIRECTORY_SEPARATOR.'coverflow'.DIRECTORY_SEPARATOR.'actions'.DIRECTORY_SEPARATOR);

$header = '<link rel="stylesheet" href="'.CHEMIN.'presentations'.DIRECTORY_SEPARATOR.'imageflow.css" type="text/css" media="screen" />
<script type="text/javascript" src="'.CHEMIN.'js'.DIRECTORY_SEPARATOR.'imageflow.js"></script>
<script type="text/javascript"';

if ($this->GetMethod() == "show") {
	$plugin_output_new=preg_replace ('/<script type="text\/javascript"/', $header,	$plugin_output_new, 1);
}