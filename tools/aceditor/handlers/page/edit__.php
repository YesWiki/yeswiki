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
		<input name=\"submit\" type=\"submit\" class=\"ACEsubmit\" value=\"Sauver\" accesskey=\"s\" />
		<input name=\"submit\" type=\"submit\" class=\"ACEpreview\" value=\"Aper&ccedil;u\" accesskey=\"p\" />
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'**','**');\" src=\"tools/aceditor/ACEdImages/bold.gif\" title=\"Passe le texte s&eacute;lectionn&eacute; en gras  ( Ctrl-Maj-b )\" alt=\"Gras\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'//','//');\" src=\"tools/aceditor/ACEdImages/italic.gif\" title=\"Passe le texte s&eacute;lectionn&eacute; en italique ( Ctrl-Maj-t )\" alt=\"Italique\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'__','__');\" src=\"tools/aceditor/ACEdImages/underline.gif\" title=\"Souligne le texte s&eacute;lectionn&eacute; ( Ctrl-Maj-u )\" alt=\"Soulign&eacute;\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'@@','@@');\" src=\"tools/aceditor/ACEdImages/strike.gif\" title=\"Barre le texte s&eacute;lectionn&eacute;\" alt=\"Barre\" />		    
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />		    
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'======','======\\n');\" src=\"tools/aceditor/ACEdImages/t1.gif\" title=\" En-t&ecirc;te &eacute;norme\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'=====','=====\\n');\" src=\"tools/aceditor/ACEdImages/t2.gif\" title=\"  En-t&ecirc;te tr&egrave;s gros\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'====','====\\n');\" src=\"tools/aceditor/ACEdImages/t3.gif\" title=\"  En-t&ecirc;te gros\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'===','===\\n');\" src=\"tools/aceditor/ACEdImages/t4.gif\" title=\"  En-t&ecirc;te normal\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'==','==');\" src=\"tools/aceditor/ACEdImages/t5.gif\" title=\"  Petit en-t&ecirc;te\" alt=\"\" />
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionWithLink(thisForm.body);\" src=\"tools/aceditor/ACEdImages/link.gif\" title=\"Ajoute un lien au texte s&eacute;lectionn&eacute;\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'\\t-&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listepuce.gif\" title=\"Liste\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'\\t1)&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listenum.gif\" title=\"Liste num&eacute;rique\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'\\ta)&nbsp;','');\" src=\"tools/aceditor/ACEdImages/listealpha.gif\" title=\"Liste alphab&eacute;thique\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionBis(thisForm.body,'\\n---','');\" src=\"tools/aceditor/ACEdImages/crlf.gif\" title=\"Ins&egrave;re un retour chariot\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionBis(thisForm.body,'\\n------','');\" src=\"tools/aceditor/ACEdImages/hr.gif\" title=\"Ins&egrave;re une ligne horizontale\" alt=\"\" />			
		<img class=\"buttons\"  src=\"tools/aceditor/ACEdImages/separator.gif\" alt=\"\"  />		      
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'%%','%%');\" src=\"tools/aceditor/ACEdImages/code.gif\" title=\"Code\" alt=\"\" />
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelection(thisForm.body,'%%(php)','%%');\" src=\"tools/aceditor/ACEdImages/php.gif\" title=\"Code PHP\" alt=\"\" />
		</div>
		<div id=\"toolbar_suite\">   		   
		<img class=\"buttons\" onmouseover=\"mouseover(this);\" onmouseout=\"mouseout(this);\" onmousedown=\"mousedown(this);\" onmouseup=\"mouseup(this);\" onclick=\"wrapSelectionWithImage(thisForm.body);\" src=\"tools/aceditor/ACEdImages/image.gif\"    title=\"ins&egrave;re un tag image \" alt=\"\" />		
		<span class=\"texteChampsImage\">
		&nbsp;&nbsp;Fichier&nbsp;<input type=\"text\" name=\"filename\" class=\"ACsearchbox\" size=\"10\"/>&nbsp;&nbsp;Description&nbsp;<input type=\"text\" name=\"description\" class=\"ACsearchbox\" size=\"10\"/>
		&nbsp;&nbsp;Alignement&nbsp;<select id=\"alignment\" class=\"ACsearchbox\">
		<option value=\"left\">Gauche</option>
		<option value=\"center\">Centr&eacute;</option>
		<option value=\"right\">Droite</option>
		</select>
		</span>
		</div>";
		
			if (substr(WIKINI_VERSION,2,1)<=4) {

			$plugin_output_new=preg_replace ('/\<textarea onkeydown/',
			$ACbuttonsBar.
			'<textarea onkeydown',
			$plugin_output_new);
		
			}
			else  {
				if (substr(WIKINI_VERSION,2,1)>=5) {
						$plugin_output_new=preg_replace ('/\<textarea id="body" name="body" cols="60" rows="40" wrap="soft"/',
						$ACbuttonsBar.
						'<textarea id="body" name="body" cols="60" rows="40" ',
						$plugin_output_new);
					
				}
			}
		
		
		
	}
}
