<?php
/*
*/

// V�rification de s�curit�
if (!defined("WIKINI_VERSION"))
{
	die ("acc&egrave;s direct interdit");
}


if ($this->HasAccess("read"))
{
	if (!$this->page)
	{
		return;
	}
	else
	{
		// on r�cup�re les ent�tes html mais pas ce qu'il y a dans le body
		$header =  explode('<body',$this->Header());
		echo $header[0]."<body>\n<div class=\"page-widget\">\n";
		
		//affichage de la page format�e
		echo $this->Format($this->page["body"]);
		echo "</div><!-- end div.page-widget -->";
		
		//javascript pour gerer les liens (ouvrir vers l'ext�rieur) dans les iframes
		$scripts_iframe = '<script>
		$(document).ready(function () {
			$("html").css({\'overflow-y\': \'auto\'});
			$("body").css({
							\'background-color\' : \'transparent\',
							\'background-image\' : \'none\',
							\'text-align\' : \'left\',
							\'width\' : \'auto\',
							\'min-width\' : \'0\',
						});
			
			$("a[href^=\'http://\']:not(a[href$=\'/slide_show\'])").click(function() {
				if (window.location != window.parent.location)
				{
					if (!($(this).hasClass("bouton_annuler")))
					{
						window.open($(this).attr("href"));
						return false;
					}
				}
			});			
		});
		</script>';		
		$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$scripts_iframe ;
		
		//on r�cup�re juste les javascripts et la fin des balises body et html
		$footer =  preg_replace('/^.+<script/Us', '<script', $this->Footer());
		echo $footer;
		
	}
}
else
{
	return;
}
?>
