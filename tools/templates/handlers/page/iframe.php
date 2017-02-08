<?php
/*
 */

// Verification de securite
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

if ($this->HasAccess("read")) {
    if (!$this->page) {
        return;
    } else {
        $output = '';
        // on recupere les entetes html mais pas ce qu'il y a dans le body
        $header = explode('<body', $this->Header());
        $output .= $header[0] . "<body class=\"yeswiki-body\">\n<div class=\"yeswiki-page-widget page-widget page\">\n";

        // par defaut on ajoute un bouton de partage, mais il peut etre desactive en ajoutant &share=0 à l'url de l'iframe
        if (isset($_GET['share']) && $_GET['share'] == '1') {
            $output .= '<a class="btn btn-small btn-default link-share modalbox pull-right" href="' . $this->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' ' . $this->GetPageTag() . '"><i class="icon-share"></i>&nbsp;' . _t('TEMPLATE_SHARE') . '</a>';
        }

        // affichage de la page formatee
        if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
            // pas de modification des urls
            $output .= $this->Format($this->page["body"]);
        } else {
            // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
            $pattern = ',' . preg_quote($this->config['base_url']) . '(\w+)([&#?].*?)?(["<]),';
            $output .= preg_replace($pattern, $this->config['base_url'] . "$1/iframe$2$3", $this->Format($this->page["body"]));
        }
        $output .= "</div><!-- end div.page-widget -->";

        // par defaut on ajoute la barre de modification, mais elle peut etre desactivee en ajoutant &edit=0 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->Format('{{barreredaction}}');
        }

        // javascript pour gerer les liens (ouvrir vers l'exterieur) dans les iframes
        $scripts_iframe = '<script>

		$(document).ready(function () {
      // Get height of the main element in the iframe document
      var documentHeight = document.getElementsByClassName("page-widget")[0].scrollHeight

      // Add some unique identifier to the string being passed
      var message = "documentHeight:"+documentHeight+"&urlIframe:"+window.location.href;

      // Pass message to (any*) parent document
      parent.postMessage(message,"*");


      // On resize of the window, recalculate the height of the main element, and pass to the parent document again
      window.onresize = function(event) {
        //console.log(document.getElementsByClassName("page-widget")[0]);
      	var newDocumentHeight = document.getElementsByClassName("page-widget")[0].scrollHeight;
      	var heightDiff = documentHeight - newDocumentHeight;

      	// If difference between current height and new height is more than 10px
      	if ( heightDiff > 10 | heightDiff < -10 ) {

      		documentHeight = newDocumentHeight;
      		message = "documentHeight:"+documentHeight+"&urlIframe:"+window.location.href;
      		parent.postMessage(message,"*");
      	}

      }

			$("iframe").load(function() {
				this.scroll(0,0);
				$(window.parent.document).scroll(0,0);
				//$(this).find(".modalbox").removeClass("modalbox").click(function(event){
					//event.stopPropagation();
					//document.location = $(this).attr("href");
					//return false;
				//});
				//$(this).find("form").on("submit", function() {$(window.parent.document).scrollTop(0);});
			});
			$("a[href^=\'http://\']:not(a[href*=\'/slide_show\'], a[href*=\'/iframe\'], a.modalbox, a.fc-event, a[target])").click(function() {
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
				background-color : transparent;
				background-image : none;
			}
			.yeswiki-body {
				background-color : transparent;
				background-image : none;
				text-align : left;
				width : auto;
				min-width : 0;
				padding-top : 0;
			}
			.yeswiki-page-widget { min-height:auto !important; }
		</style>' . "\n";

        $GLOBALS['js'] = ((isset($GLOBALS['js'])) ? $GLOBALS['js'] : '') . $scripts_iframe;
        $this->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', $styleiframe . '<script', $this->Footer());
        echo $output;

    }
} else {
    return;
}
