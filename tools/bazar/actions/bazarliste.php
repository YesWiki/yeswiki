<?php

// Display data collected by a specific Form
// A lot of parameters are available to customize the display (List, Map, Calendar, pagination, filtering...)

// Security test
if (!defined('WIKINI_VERSION')) {
    die('acc&egrave;s direct interdit');
}

$this->AddJavascriptFile('tools/bazar/libs/bazar.js');
$GLOBALS['params'] = getAllParameters($this);

// Get results
if (is_array($GLOBALS['params']['idtypeannonce'])) {
    $results = array();
    foreach ($GLOBALS['params']['idtypeannonce'] as $formid) {
        $results = array_merge(
            $results,
            baz_requete_recherche_fiches($GLOBALS['params']['query'], 'alphabetique', $formid, '', 1, '', '', true, '')
        );
    }
} else {
    $results = baz_requete_recherche_fiches($GLOBALS['params']['query'], 'alphabetique', '', '', 1, '', '', true, '');
}

// Render the view
if ($GLOBALS['params']['search'] == "true") {
  echo baz_rechercher(
      $GLOBALS['params']['idtypeannonce'],
      $GLOBALS['params']['categorienature']
  );
} else {
  echo displayResultList($results, $GLOBALS['params'], false);
}
