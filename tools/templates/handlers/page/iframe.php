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
        $output .= $header[0] . '<body class="yeswiki-body">'."\n".'<div class="yeswiki-page-widget page-widget page" '.$this->Format('{{doubleclic iframe="1"}}').'>'."\n";

        // on ajoute un bouton de partage, mais il peut etre desactive en ajoutant &share=0 à l'url de l'iframe
        if (isset($_GET['share']) && $_GET['share'] == '1') {
            $output .= '<a class="btn btn-small btn-default link-share modalbox pull-right" href="' . $this->href('share') . '" title="' . _t('TEMPLATE_SEE_SHARING_OPTIONS') . ' ' . $this->GetPageTag() . '"><i class="glyphicon glyphicon-share"></i>&nbsp;' . _t('TEMPLATE_SHARE') . '</a>';
        }

        // affichage de la page formatee
        if (isset($_GET['iframelinks']) && $_GET['iframelinks'] == '0') {
            // pas de modification des urls
            $output .= $this->Format($this->page["body"]);
        } else {
            // pattern qui rajoute le /iframe pour les liens au bon endroit, merci raphael@tela-botanica.org
            $pattern = ',' . preg_quote($this->config['base_url']) . '(\w+)([&#?].*?)?(["<]),';
            $pagebody = preg_replace(
                $pattern,
                $this->config['base_url'] . "$1/iframe$2$3",
                $this->Format($this->page["body"])
            );

            // pattern qui rajoute le /editiframe pour les liens au bon endroit
            $pattern = ',' . preg_quote($this->config['base_url']) . '(\w+)\/edit([&#?].*?)?(["<]),';
            $pagebody = preg_replace(
                $pattern,
                $this->config['base_url'] . "$1/editiframe$2$3",
                $pagebody
            );

            // on ajoute au contenu
            $output .= $pagebody;
        }
        $output .= '</div><!-- end div.page-widget -->'."\n";

        // par defaut on ajoute la barre de modification, mais elle peut etre desactivee en ajoutant &edit=0 à l'url de l'iframe
        if (isset($_GET['edit']) && $_GET['edit'] == '1') {
            $output .= $this->Format('{{barreredaction}}');
        }

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

        $this->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
        // on recupere juste les javascripts et la fin des balises body et html
        $output .= preg_replace('/^.+<script/Us', $styleiframe . '<script', $this->Footer());
        echo $output;

    }
} else {
    return;
}
