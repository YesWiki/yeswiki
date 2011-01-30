<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->GetMethod() == "show" || $this->GetMethod() == "bazarframe" || $this->GetMethod() == "edit") {
	$javascript = '<script type="text/javascript" src="tools/bazar/libs/bazar.js"></script>'."\n";
	$plugin_output_new = preg_replace ('/<\/body>/', $javascript."\n".'</body>', $plugin_output_new);	
}