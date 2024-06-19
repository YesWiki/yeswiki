<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

//parametres wikini
$pagetag = trim($this->GetParameter('page'));
if (empty($pagetag)) {
    return '<div class="error_box">' . _t('DIAPORAMA_PAGE_PARAM_MISSING') . '</div>';
}

$class = trim($this->GetParameter('class'));

$template = trim($this->GetParameter('template'));
if (empty($template)) {
    $template = 'diaporama_slides.tpl.html';
} elseif (!file_exists('tools/templates/presentation/templates/' . basename($template))) {
    echo '<div class="error_box">' . _t('DIAPORAMA_TEMPLATE_PARAM_ERROR') . '</div>';
    $template = 'diaporama_slides.tpl.html';
}

//pour l'action diaporama, on simule la presence sur la page, afin qu'il recupere les fichiers attaches au bon endroit
$oldpage = $this->GetPageTag();
$this->tag = $pagetag;
$this->page = $this->LoadPage($this->tag);

//fonction de generation du diaporama (teste les droits et l'existence de la page)
echo $this->services->get(\YesWiki\Templates\Service\Utils::class)->printDiaporama($pagetag, $template, $class);

//on retablie le bon nom de page
$this->tag = $oldpage;
$this->page = $this->LoadPage($oldpage);
