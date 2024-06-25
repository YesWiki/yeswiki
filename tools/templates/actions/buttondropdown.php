<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// texte genere a l'interieur du bouton
$text = $this->GetParameter('text');

// titre au survol du bouton et dans la boite modale associée
$title = $this->GetParameter('title');

// mettre un petit triangle pour indiquer que c'est déroulant
$caret = $this->GetParameter('caret');
if ($caret != '0') {
    $caret = '1';
}

// icone du bouton
$icon = trim($this->GetParameter('icon'));
if (!empty($icon)) {
    // si le parametre contient des espaces, il s'agit d'une icone autre que celles par defaut de bootstrap

    if (preg_match('/\s/', $icon) === 1) {
        $icon = '<i class="' . $icon . '"></i>';
    } else {
        $icon = '<i class="icon-' . $icon . ' fa fa-' . $icon . '"></i>';
    }
    if (!empty($text)) {
        $icon = $icon . ' ';
    }
}

// classe css supplémentaire l'ensemble du
$class = $this->GetParameter('class');

// classe css supplémentaire pour changer le look des boutons
$btnclass = $this->GetParameter('btnclass');
$btnclass = 'btn ' . $btnclass;
if (!strstr($btnclass, 'btn-')) {
    $btnclass .= ' btn-default';
}

$nobtn = $this->GetParameter('nobtn');
if (!empty($nobtn) && $nobtn == '1') {
    $btnclass = str_replace(['btn ', 'btn-default'], ['', ''], $btnclass);
}

$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['buttondropdown'])) {
    $GLOBALS['check_' . $pagetag]['buttondropdown'] = $this->services->get(\YesWiki\Templates\Service\Utils::class)->checkGraphicalElements('buttondropdown', $pagetag, $this->page['body'] ?? '');
}
if ($GLOBALS['check_' . $pagetag]['buttondropdown']) {
    $encodedtitle = htmlentities($title, ENT_COMPAT, YW_CHARSET);
    echo '<div class="btn-group' . (!empty($class) ? ' ' . $class : '') . '"> <!-- start of buttondropdown -->
  <button class="' . $btnclass . ' dropdown-toggle" data-toggle="dropdown" aria-label="' . $encodedtitle . '" title="' . $encodedtitle . '">
    ' . $icon . $text . (($caret == '1') ? ' <span class="caret"></span>' : '') . '
  </button>' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_BUTTONDROPDOWN') . '</strong> : ' . _t('TEMPLATE_ELEM_BUTTONDROPDOWN_NOT_CLOSED') . '.</div>' . "\n";

    return;
}
