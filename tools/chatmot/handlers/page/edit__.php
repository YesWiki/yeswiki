<?php

if (!defined("WIKINI_VERSION"))
{
            die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write") && $this->HasAccess("read"))
{
	// preview?
	if ($_POST["submit"] == "Aperçu")
	{
		// Rien
	}
	else
	{
		$ACbuttonsBarPage = "
	
		<div id=\"toolbar_chameau\">
		<span class=\"texteChampsPage\">&nbsp;Nouvelle page&nbsp;:&nbsp;<input type=\"text\" name=\"nouvellepage\" class=\"ACsearchbox\" size=\"25\"/>
		<a href=\"#\" class=\"ok\" onclick=\"wrapSelectionWithPage(thisForm.body);\">Créer</a> 
		</span>
		</div>";
		
			if (substr(WIKINI_VERSION,2,1)<=4) {

			$plugin_output_new=preg_replace ('/\<textarea onkeydown/',
			$ACbuttonsBarPage.
			'<textarea onkeydown',
			$plugin_output_new);
		
			}
			else  {
				if (substr(WIKINI_VERSION,2,1)>=5) {
						$plugin_output_new=preg_replace ('/\<textarea id="body"/',
						$ACbuttonsBarPage.
						'<textarea id="body"',
						$plugin_output_new);
					
				}
			}
		
		
		
	}
}