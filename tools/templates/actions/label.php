<?php

/*
label.php

2017 Florian Schmitt

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

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
if (!isset($GLOBALS['check_' . $pagetag]['label'])) {
    $GLOBALS['check_' . $pagetag]['label'] = check_graphical_elements('label', $pagetag, $this->page['body']);
}
if ($GLOBALS['check_' . $pagetag]['label']) {
    echo '<!-- start of label -->
    <span' . (!empty($id) ? ' id="'.$id .'"' : '') . ' class="label '. $class . '"';
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            echo ' data-'.$key.'="'.$value.'"';
        }
    }
    echo '>' . "\n";
} else {
    echo '<div class="alert alert-danger"><strong>' . _t('TEMPLATE_ACTION_LABEL') . '</strong> : '
        . _t('TEMPLATE_ELEM_LABEL_NOT_CLOSED') . '.</div>' . "\n";
    return;
}
