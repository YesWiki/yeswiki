<?php

// ---------------------
// Data for the template
// ---------------------
$data = baz_forms_and_lists_ids();
// Bazar actions documentation, read from Yaml file
require_once "vendor/Spyc.php";
$data['config'] = Spyc::YAMLLoad('tools/bazar/actions/documentation.yaml');


// ---------------
// Render Template
// ---------------
echo $this->Header();
include_once 'includes/squelettephp.class.php';
try {
    $squel = new SquelettePhp('bazar-actions-builder.tpl.html', 'aceditor');
    echo $squel->render(['data' => $data]);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erreur handler widget : ',  $e->getMessage(), '</div>'."\n";
}
echo $this->Footer();
