<?php

// Display data collected by a specific Form
// A lot of parameters are available to customize the display (List, Map, Calendar, pagination, filtering...)

// Security test
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

global $bazarFiche;

$this->AddJavascriptFile('tools/bazar/libs/bazar.js');
$GLOBALS['params'] = getAllParameters($this);

// Get results
if (is_array($GLOBALS['params']['idtypeannonce'])) {
    $fiches = array();
    foreach ($GLOBALS['params']['idtypeannonce'] as $formId) {
        $fiches = array_merge(
            $fiches,
            $bazarFiche->search(['queries' => $GLOBALS['params']['query'], 'formsIds' => [$formId]])
        );
    }
} else {
    $fiches = $bazarFiche->search(['queries' => $GLOBALS['params']['query']]);
}

// Render the view
if (getParameter_boolean($this, 'search', false)) {
  echo baz_rechercher($GLOBALS['params']['idtypeannonce'], $GLOBALS['params']['categorienature']);
} else {
  echo displayResultList($fiches, $GLOBALS['params'], false);
}
