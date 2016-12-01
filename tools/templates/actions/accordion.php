<?php
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

// classe css supplémentaire
$class = $this->GetParameter('class');
$class = 'row-fluid row'.((!empty($class)) ? ' '.$class : '');
// data attributes
$data = getDataParameter();
$pagetag = $this->GetPageTag();

// teste s'il y a bien un element de fermeture associé avant d'ouvrir une balise
if (!isset($GLOBALS['check_'.$pagetag]['accordion'])) {
    $GLOBALS['check_'.$pagetag ]['accordion'] = check_graphical_elements('accordion', $pagetag, $this->page['body']);
}

if ($GLOBALS['check_'.$pagetag]['accordion']) {
    $accordionID = uniqid('accordion_');
    $GLOBALS['check_'.$pagetag ]['accordion_uniqueID'] = $accordionID;

    $data = "";
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data .= ' data-'.$key.'="'.$value.'"';
        }
    }

    echo " <!-- start of accordion -->
        <div class=\"panel-group $class \" role=\"tablist\" aria-multiselectable=\"true\" id=\"$accordionID\" $data>";

} else {
    echo '<div class="alert alert-danger"><strong>'._t('TEMPLATE_ACTION_ACCORDION').'</strong> : '._t('TEMPLATE_ELEM_ACORDION_NOT_CLOSED').'.</div>'."\n";
    return;
}
