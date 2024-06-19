<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = 'row-fluid row' . ((!empty($class)) ? ' ' . $class : '');
// data attributes
$data = $this->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();
$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['grid'])) {
    $GLOBALS['check_' . $pagetag]['grid'] = $this->services->get(\YesWiki\Templates\Service\Utils::class)->checkGraphicalElements('grid', $pagetag, $this->page['body'] ?? '');
}

if ($GLOBALS['check_' . $pagetag]['grid']) {
    echo '<div class="' . $class . '">';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-' . $key . '="' . $value . '"';
        }
    }
    echo ' <!-- start of grid -->' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_GRID') . '</strong> : ' . _t('TEMPLATE_ELEM_GRID_NOT_CLOSED') . '.</div>' . "\n";

    return;
}
