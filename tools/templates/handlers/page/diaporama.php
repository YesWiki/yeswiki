<?php

// Verification de securite
if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}
ob_start();

// on regarde si un template est passe en parametre GET, on passe celui par defaut sinon
if (isset($_GET['template']) && file_exists(realpath('tools/templates/presentation/templates/' . $_GET['template']))) {
    $template = basename(realpath('tools/templates/presentation/templates/' . $_GET['template']));
} else {
    $template = 'diaporama_slides.tpl.html';
}

// fonction de generation du diaporama (teste les droits et l'existence de la page)
echo $this->services->get(\YesWiki\Templates\Service\Utils::class)->printDiaporama($this->tag, $template);

$output = ob_get_contents();
ob_end_clean();

// on recupere les entetes html mais pas ce qu'il y a dans le body
$header = explode('<body', $this->Header());
echo str_replace('<html', '<html class="slideshow-html"', $header[0]) . "<body class=\"slideshow-body\">\n";
echo $output;

// on recupere juste les javascripts et la fin des balises body et html
$footer = preg_replace('/^.+<script/Us', "\n" . '<script', $this->Footer());

echo $footer;
