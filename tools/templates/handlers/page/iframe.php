<?php
/*
*/

// Verification de securite
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
		$output = '';
		// on recupere les entetes html mais pas ce qu'il y a dans le body
		$header =  explode('<body',$this->Header());
		$output .= $header[0]."<body>\n<div class=\"page-widget\">\n";	

		// par defaut on ajoute un bouton de partage, mais il peut etre desactive en ajoutant &share=0 à l'url de l'iframe
		if (isset($_GET['share']) && $_GET['share'] == '0') {
			// pas de bouton de partage
		}
		else {
			$output .= '<a class="btn btn-small btn-default link-share modalbox pull-right" href="'.$this->href('share').'" title="'.TEMPLATE_SEE_SHARING_OPTIONS.' '.$this->GetPageTag().'"><i class="icon-share"></i>&nbsp;'.TEMPLATE_SHARE.'</a>';
		}

		// affichage de la page formatee
		// pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
		$pattern = ','.preg_quote($this->config['base_url']).'(\w+)([&#?].*?)?(["<]),';
		$output .= preg_replace($pattern, $this->config['base_url']."$1/iframe$2$3", $this->Format($this->page["body"]));
		$output .= "</div><!-- end div.page-widget -->";
		
		// par defaut on ajoute la barre de modification, mais elle peut etre desactivee en ajoutant &edit=0 à l'url de l'iframe
		if (isset($_GET['edit']) && $_GET['edit'] == '0') {
			// pas de barre d'edition
		}
		else {
			$output .= $this->Format('{{barreredaction}}');
		}

		// javascript pour gerer les liens (ouvrir vers l'exterieur) dans les iframes
		$scripts_iframe = '<script>
		$(document).ready(function () {
			$("a[href^=\'http://\']:not(a[href$=\'/slide_show\'], a[href$=\'/iframe\'])").click(function() {
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

		// on efface le style par defaut du fond pour l'iframe
		$styleiframe = '<style>
			html {
				overflow-y: auto;
			}
			body {
				background-color : transparent,
				background-image : none,
				text-align : left,
				width : auto,
				min-width : 0,
			}
		</style>'."\n";


		$GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '').$scripts_iframe ;
		
		// on recupere juste les javascripts et la fin des balises body et html
		$output .=  preg_replace('/^.+<script/Us', $styleiframe.'<script', $this->Footer());
		echo $output;
		
	}
}
else
{
	return;
}
?>
