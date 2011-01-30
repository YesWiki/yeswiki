<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}



if ($this->GetMethod() == "edit") {
	$plugin_output_new=preg_replace ('/<\/head>/',
	'
	<style type="text/css">
	.buttons { background: #ccc; border: 1px solid #ccc; margin: 1; float:left; }
	.raise{ border-top: 1px solid buttonhighlight; border-left: 1px solid buttonhighlight; border-bottom: 1px solid buttonshadow; border-right: 1px solid buttonshadow; background: #ccc; margin:1;    float:left; }
	.press { border-top: 1px solid buttonshadow; border-left: 1px solid buttonshadow; border-bottom: 1px solid buttonhighlight; border-right: 1px solid buttonhighlight; background: #ccc; margin:1; float:left; }
	/* ci dessous les petits champs */
	.ACsearchbox { background: #FFFFF8; border: 0px; border-bottom: 1px solid #CCCCAA; padding: 0px; margin: 0px; font-size: 10px; }
	.texteChampsImage {font-size: 10px; }
	#toolbar, #toolbar_suite { margin:0; width: 100%; padding:0; height:20px; background: #ccc; border-top: 1px solid buttonhighlight; border-left: 1px solid buttonhighlight; border-bottom: 1px solid buttonshadow; border-right: 1px solid buttonshadow; text-align:left; }
	#toolbar a, #toolbar_suite a {color:#000;}
	.edit {	border:1px solid #666666; width:100%; }
	.ACEsubmit {float:left;border:0; background:url(tools/aceditor/ACEdImages/save.png) left top no-repeat;height:16px;width:16px;cursor:pointer; margin:1px; color:#77a0de; font-size:0;}
	.ACEpreview {float:left;border:0; background:url(tools/aceditor/ACEdImages/apercu.png) left top no-repeat;height:16px;width:16px;cursor:pointer; margin:1px; color:#77a0de; font-size:0;}
	</style>
	<script type="text/javascript" src="tools/aceditor/ACeditor.js"></script> 
	</head>   
	',
	$plugin_output_new);
	

	$plugin_output_new=preg_replace ('/<body /',
	"<body onload=\"thisForm=document.ACEditor;\""
	,
	$plugin_output_new);
	
}	
