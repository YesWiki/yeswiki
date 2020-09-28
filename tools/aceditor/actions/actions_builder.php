<?php

// ---------------------
// Data for the template
// ---------------------
$data = baz_forms_and_lists_ids();
// Bazar actions documentation, read from Yaml file
require_once "vendor/Spyc.php";
$docFiles = glob('docs/actions/*.{yml,yaml}', GLOB_BRACE);
$extenstionDocFiles = glob('tools/**/actions/documentation.{yml,yaml}', GLOB_BRACE);
$docFiles = array_merge($docFiles, $extenstionDocFiles);
$data['action_groups'] = [];
foreach($docFiles as $filePath) {
  $filename = pathinfo($filePath)['filename'];
  $data['action_groups'][$filename] = Spyc::YAMLLoad($filePath);
  // When order is not defined, put at the end
  if (!$data['action_groups'][$filename]['position']) $data['action_groups'][$filename]['position'] = 1000;
}
// Sort by position
uasort($data['action_groups'], function($a, $b) {
  return $a['position'] - $b['position'];
});

// ---------------
// Render Template
// ---------------
include_once 'includes/squelettephp.class.php';
try {
    $squel = new SquelettePhp('actions-builder.tpl.html', 'aceditor');
    echo $squel->render(['data' => $data]);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erreur handler widget : ',  $e->getMessage(), '</div>'."\n";
}
