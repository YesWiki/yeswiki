<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	// preview?
	if (isset($_POST["submit"]) && $_POST["submit"] == "Aperçu")
	{
		// Rien
	}
	else
	{
		/*$ACbuttonsBar .= '<div class="btn-toolbar">
		<div class="btn-group">
          <button class="btn btn-primary" value="Sauver" name="submit" type="submit">Sauver</button>
        </div>
        <div class="btn-group">
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'**\',\'**\');" title="'.ACEDITOR_BOLD.'">
          	<span style="font-family:serif;font-weight:bold;">B</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'//\',\'//\');" title="'.ACEDITOR_ITALIC.'">
          	<span style="font-family:serif;font-style:italic;">I</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'__\',\'__\');" title="'.ACEDITOR_UNDERLINE.'">
          	<span style="font-family:serif;text-decoration:underline;">U</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'@@\',\'@@\');" title="'.ACEDITOR_STRIKE.'">
         	<span style="font-family:serif;text-decoration:line-through;">S</span>
          </a>
        </div>
        <div class="btn-group">
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'======\',\'======\');" title="'.ACEDITOR_TITLE1.'">
          	<span style="font-size:15px;font-weight:bold;">T1</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'=====\',\'=====\');" title="'.ACEDITOR_TITLE2.'">
          	<span style="font-size:14px;font-weight:bold;">T2</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'====\',\'====\');" title="'.ACEDITOR_TITLE3.'">
          	<span style="font-size:13px;font-weight:bold;">T3</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'===\',\'===\');" title="'.ACEDITOR_TITLE4.'">
          	<span style="font-size:11px;font-weight:bold;">T4</span>
          </a>
          <a class="btn" onclick="wrapSelection($(\'#body\')[0],\'==\',\'==\');" title="'.ACEDITOR_TITLE5.'">
          	<span style="font-size:10px;font-weight:bold;">T5</span>
          </a>
        </div>
        <div class="btn-group">
          <a class="btn" onclick="wrapSelectionBis($(\'#body\')[0],\'\\n------\',\'\');" title="'.ACEDITOR_HORIZONTAL_LINE.'">
          	<i class="icon-minus"></i>
          </a>
          <a class="btn" onclick="wrapSelectionWithLink($(\'#body\')[0]);" title="'.ACEDITOR_LINK.'">
          	<i class="icon-share-alt"></i>&nbsp;Lien
          </a>
        </div>
      </div>';
		
		$plugin_output_new = preg_replace ('/\<form id=\"ACEditor\"/',
											$ACbuttonsBar.'<form id="ACEditor"',
											$plugin_output_new);*/
	}
}
