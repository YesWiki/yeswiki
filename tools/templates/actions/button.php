<?php

use YesWiki\Core\Service\LinkTracker;

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// adresse vers quoi le bouton pointe
$link = $this->GetParameter('link');

// extration du nom de 'root_page' si nécessaire
if ($link == 'config/root_page') {
    $link = $this->config['root_page'];
    $this->setParameter('link', $link);
}

$linkParts = $this->extractLinkParts($link);
if ($linkParts) {
    $link = $this->href($linkParts['method'], $linkParts['tag'], $linkParts['params']);
    $this->services->get(LinkTracker::class)->forceAddIfNotIncluded($linkParts['tag']);
}
// change short yeswiki urls in real links
$link = $this->generateLink($link);

// texte genere a l'interieur du bouton
$text = $this->GetParameter('text');

// titre au survol du bouton et dans la boite modale associée
$title = $this->GetParameter('title');

// icone du bouton
$icon = trim($this->GetParameter('icon'));
if (!empty($icon)) {
    // si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap
    if (preg_match('/\s/', $icon)) {
        $icon = '<i class="' . $icon . '"></i>';
    } else {
        $icon = '<i class="icon-' . $icon . ' fa fa-' . $icon . '"></i>';
    }
    if (!empty($text)) {
        $icon = $icon . ' ';
    }
}

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class .= (!empty($class) ? ' ' : '') . 'btn';
if (!preg_match('/\bbtn-\w+\b/i', $class)) {
    $class .= ' btn-default';
}

$datasize = '';
if (preg_match('/\bmodalbox\b/i', $class)) {
    // if modalbox, set the big size
    $datasize .= 'modal-lg';
}

$nobtn = $this->GetParameter('nobtn');
if (!empty($nobtn) && $nobtn == '1') {
    // remove all the btn or btn-* css class
    $class = preg_replace('/\b(?:btn(?!-)|btn-\w+)\b/i', '', $class);
    // remove unneeded spaces
    $class = preg_replace('/(^\s*)|(\s*$)/', '', preg_replace('/\s{2,}/', ' ', $class));
}

$hideIfNoAccess = $this->GetParameter('hideifnoaccess');
if ($hideIfNoAccess == 'true' && isset($linkParts['tag']) && !$GLOBALS['wiki']->HasAccess('read', $linkParts['tag'])) {
    echo '';
} elseif (empty($link)) {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_BUTTON') . '</strong> : ' . _t('TEMPLATE_LINK_PARAMETER_REQUIRED') . '.</div>' . "\n";
} else {
    $btn = '<a'
        . (!empty($link) ? ' href="' . $link . '"' : '')
        . (!empty($class) ? ' class="' . $class . '"' : '')
        . (!empty($datasize) ? ' data-size="' . $datasize . '"' : '')
        . ((!empty($datasize) && empty($linkParts)) ? ' data-iframe="1"' : '') // use iframe for external links in modalbox
        . (!empty($title) ? ' title="' . htmlentities($title, ENT_COMPAT, YW_CHARSET) . '"' : '');
    $btn .= '>' . $icon . (!empty($text) ? htmlentities($text, ENT_COMPAT, YW_CHARSET) : '') . '</a>';
    echo $btn;
}
