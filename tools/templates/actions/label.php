<?php

// Get the action's parameters :

// label class
$class = $this->GetParameter('class');
if (empty($class)) {
    $class = 'label-default';
}

// label id
$id = $this->GetParameter('id');

// label data attributes
$data = getDataParameter();

$pagetag = $this->GetPageTag();
if (!isset($GLOBALS['check_'.$pagetag])) {
    $GLOBALS['check_'.$pagetag] = [];
}
if (!isset($GLOBALS['check_' . $pagetag]['label'])) {
    $GLOBALS['check_' . $pagetag]['label'] = check_graphical_elements('label', $pagetag, $this->page['body'] ?? '');
}
if ($GLOBALS['check_' . $pagetag]['label']) {
    echo '<span' . (!empty($id) ? ' id="'.$id .'"' : '') . ' class="label '. $class . '"';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-'.$key.'="'.$value.'"';
        }
    }
    echo '>';
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_LABEL') . '</strong> : '
        . _t('TEMPLATE_ELEM_LABEL_NOT_CLOSED') . '.</div>' . "\n";
    return;
}
