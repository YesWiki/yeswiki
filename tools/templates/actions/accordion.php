<?php

if (!defined('WIKINI_VERSION')) {
    exit('acc&egrave;s direct interdit');
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = ((!empty($class)) ? ' ' . $class : '');
// data attributes
$data = $this->services->get(\YesWiki\Templates\Service\Utils::class)->getDataParameter();
$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag])) {
    $GLOBALS['check_' . $pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['accordion'])) {
    $GLOBALS['check_' . $pagetag]['accordion'] = $this->services->get(\YesWiki\Templates\Service\Utils::class)->checkGraphicalElements('accordion', $pagetag, $this->page['body'] ?? '');
}

if ($GLOBALS['check_' . $pagetag]['accordion']) {
    $accordionID = uniqid('accordion_');
    $GLOBALS['check_' . $pagetag]['accordion_uniqueID'] = $accordionID;

    $data = '';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data .= ' data-' . $key . '="' . $value . '"';
        }
    }

    echo " <!-- start of accordion -->
        <div class=\"panel-group $class \" role=\"tablist\" aria-multiselectable=\"true\" id=\"$accordionID\" $data>";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_ACCORDION') . '</strong> : ' . _t('TEMPLATE_ELEM_ACORDION_NOT_CLOSED') . '.</div>' . "\n";

    return;
}
