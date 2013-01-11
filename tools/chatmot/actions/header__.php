<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}


if ($this->GetMethod() == "edit") {
	

	$plugin_output_new=preg_replace ('/<\/head>/',
	'<script type="text/javascript" src="tools/chatmot/ChatMotACeditor.js"></script>
	<style>
	#toolbar_chameau {
		background:none repeat scroll 0 0 #CCCCCC;
		border-color:buttonhighlight buttonshadow buttonshadow buttonhighlight;
		border-style:solid;
		border-width:1px;
		height:20px;
		margin:0;
		padding:0;
		text-align:left;
		width:100%;
	}
	#toolbar_chameau .ok {color:#000;}
	</style>
	</head>', $plugin_output_new);
		
}	