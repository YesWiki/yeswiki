<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// taille de la colonne
$size = $this->GetParameter('size');
if (empty($size)) {
    echo '<div><div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_COL') . '</strong> : '
        . _t('TEMPLATE_SIZE_PARAMETER_REQUIRED') . '.</div>' . "\n";
    return;
}

if (!(ctype_digit($size) && intval($size) >= 1 && intval($size) <= 12)) {
    echo '<div><div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_COL') . '</strong> : '
        . _t('TEMPLATE_SIZE_PARAMETER_MUST_BE_INTEGER_FROM_1_TO_12') . '.</div>' . "\n";
    return;
}

// classe css additionnelle
$class = $this->GetParameter('class');

// data attributes
$data = getDataParameter();

$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_' . $pagetag]['col'])) {
    $GLOBALS['check_' . $pagetag]['col'] = check_graphical_elements('col', $pagetag, $this->page['body']);
}
if ($GLOBALS['check_' . $pagetag]['col']) {
    echo '<div class="span' . $size . ' col-md-' . $size . (isset($class) ? ' ' . $class : '')
        . '"';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-'.$key.'="'.$value.'"';
        }
    }
    echo '> <!-- start of col -->' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_COL') . '</strong> : '
        . _t('TEMPLATE_ELEM_COL_NOT_CLOSED') . '.</div>' . "\n";
    return;
}
