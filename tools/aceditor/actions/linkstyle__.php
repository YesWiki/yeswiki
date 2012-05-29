<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}



if ($this->GetMethod() == "edit") {
	echo '	<link rel="stylesheet" href="tools/aceditor/presentation/styles/aceditor.css" />'."\n";
}	
