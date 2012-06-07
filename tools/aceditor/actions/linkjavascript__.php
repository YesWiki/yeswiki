<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->GetMethod() == "edit") {
	echo '	<script type="text/javascript" src="tools/aceditor/libs/ACeditor.js"></script>'."\n";
}	
