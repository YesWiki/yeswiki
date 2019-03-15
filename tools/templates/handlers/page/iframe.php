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
        $output .= $header[0] . '<body class="iframe-body">'."\n"
            .'<div class="container">'."\n"
            .'<div class="yeswiki-page-widget page-widget page" '.$this->Format('{{doubleclic iframe="1"}}').'>'."\n";

        // on ajoute un bouton de partage, si &share=1 est présent dans l'url
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
    }
} else {
    $output = '';
    // on recupere les entetes html mais pas ce qu'il y a dans le body
    $header = explode('<body', $this->Header());
    $output .= $header[0] . '<body class="login-body">'."\n"
        .'<div class="container">'."\n"
        .'<div class="yeswiki-page-widget page-widget page" '.$this->Format('{{doubleclic iframe="1"}}').'>'."\n";

    if ($contenu = $this->LoadPage("PageLogin")) {
        // si une page PageLogin existe, on l'affiche
        $output .= $this->Format($contenu["body"]);
    } else {
        // sinon on affiche le formulaire d'identification minimal
        $output .= '<div class="vertical-center white-bg">'."\n"
        .'<div class="alert alert-danger alert-error">'."\n"
        ._t('LOGIN_NOT_AUTORIZED').'. '._t('LOGIN_PLEASE_REGISTER').'.'."\n"
        .'</div>'."\n"
        .$this->Format('{{login signupurl="0"}}'."\n\n")
        .'</div><!-- end .vertical-center -->'."\n";
    }
}

$output .= '</div><!-- end .page-widget -->'."\n";

// on affiche la barre de modification, si on ajoute &edit=1 à l'url de l'iframe
if (isset($_GET['edit']) && $_GET['edit'] == '1') {
    $output .= $this->Format('{{barreredaction}}');
}
$output .= '</div><!-- end .container -->'."\n";

$this->AddJavascriptFile('tools/templates/libs/vendor/iframeResizer.contentWindow.min.js');
// on recupere juste les javascripts et la fin des balises body et html
$output .= preg_replace('/^.+<script/Us', '<script', $this->Footer());

// affichage a l'ecran
echo $output;
