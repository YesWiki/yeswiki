<?php

/*
block.php

Block opens a section HTML element.
*/

// Get the action's parameters :

// div id
$id = $this->GetParameter('id');

// div class
$class = $this->GetParameter('class');

// div style attribute
$style = $this->GetParameter('style');

// div data attributes
$data = getDataParameter();

// notifying action counter
$pagetag = $this->GetPageTag();
if (!isset($GLOBALS['check_' . $pagetag]['block'])) {
    $GLOBALS['check_' . $pagetag]['block'] = check_graphical_elements('block', $pagetag, $this->page['body']);
}
if ($GLOBALS['check_' . $pagetag]['block']) {
    
    echo '<!-- start of block -->
    <section' 
    .(!empty($id) ? ' id="'.$id .'"' : '') 
    .(!empty($class) ? ' class="'.$class .'"' : '') 
    .(!empty($style) ? ' style="'.$style .'"' : '');
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-'.$key.'="'.$value.'"';
        }
    }
    echo '>' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_BLOCK') . '</strong> : '
        . _t('TEMPLATE_ELEM_BLOCK_NOT_CLOSED') . '.</div>' . "\n";
    return;
}
