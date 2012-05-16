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
		$ACbuttonsBar = "		
		<div id=\"toolbar\"> 
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"$('#ACEditor button[value=Sauver]').click();\" src=\"tools/aceditor/ACEdImages/save.png\" title=\"Sauver la page\" alt=\"Sauver\" />
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'**','**');\" src=\"tools/aceditor/ACEdImages/bold.gif\" title=\"Passe le texte s&eacute;lectionn&eacute; en gras  ( Ctrl-Maj-b )\" alt=\"Gras\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'//','//');\" src=\"tools/aceditor/ACEdImages/italic.gif\" title=\"Passe le texte s&eacute;lectionn&eacute; en italique ( Ctrl-Maj-t )\" alt=\"Italique\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'__','__');\" src=\"tools/aceditor/ACEdImages/underline.gif\" title=\"Souligne le texte s&eacute;lectionn&eacute; ( Ctrl-Maj-u )\" alt=\"Soulign&eacute;\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'@@','@@');\" src=\"tools/aceditor/ACEdImages/strike.gif\" title=\"Barre le texte s&eacute;lectionn&eacute;\" alt=\"Barre\" />		    
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />		    
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'======','======\\n');\" src=\"tools/aceditor/ACEdImages/t1.gif\" title=\" En-t&ecirc;te &eacute;norme\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'=====','=====\\n');\" src=\"tools/aceditor/ACEdImages/t2.gif\" title=\"  En-t&ecirc;te tr&egrave;s gros\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'====','====\\n');\" src=\"tools/aceditor/ACEdImages/t3.gif\" title=\"  En-t&ecirc;te gros\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'===','===\\n');\" src=\"tools/aceditor/ACEdImages/t4.gif\" title=\"  En-t&ecirc;te normal\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'==','==');\" src=\"tools/aceditor/ACEdImages/t5.gif\" title=\"  Petit en-t&ecirc;te\" alt=\"\" />
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionWithLink($('#body')[0]);\" src=\"tools/aceditor/ACEdImages/link.gif\" title=\"Ajoute un lien au texte s&eacute;lectionn&eacute;\" alt=\"\" />
		<!-- <img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'\\t-&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listepuce.gif\" title=\"Liste\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'\\t1)&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listenum.gif\" title=\"Liste num&eacute;rique\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'\\ta)&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listealpha.gif\" title=\"Liste alphab&eacute;thique\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionBis($('#body')[0],'\\n---','');\" src=\"tools/aceditor/ACEdImages/crlf.gif\" title=\"Ins&egrave;re un retour chariot\" alt=\"\" /> -->
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionBis($('#body')[0],'\\n------','');\" src=\"tools/aceditor/ACEdImages/hr.gif\" title=\"Ins&egrave;re une ligne horizontale\" alt=\"\" />			
		<!-- <img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />		      
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'%%','%%');\" src=\"tools/aceditor/ACEdImages/code.gif\" title=\"Code\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection($('#body')[0],'%%(php)','%%');\" src=\"tools/aceditor/ACEdImages/php.gif\" title=\"Code PHP\" alt=\"\" /> -->
		</div><!-- FIN TOOLBAR ACEDITOR -->
		";
		
		$plugin_output_new = preg_replace ('/\<form id=\"ACEditor\"/',
											$ACbuttonsBar.'<form id="ACEditor"',
											$plugin_output_new);
	}
}
