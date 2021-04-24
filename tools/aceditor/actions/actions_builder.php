<?php

use Symfony\Component\Yaml\Yaml;

// ---------------------
// Data for the template
// ---------------------
$data = baz_forms_and_lists_ids();
// Bazar actions documentation, read from Yaml file
$docFiles = glob('docs/actions/*.yaml');
$extensionDocFiles = glob('tools/**/actions/documentation.yaml', GLOB_BRACE);
$customDocFiles = glob('custom/actions/documentation.yaml');
$docFiles = array_merge($docFiles, $extensionDocFiles);
$docFiles = array_merge($docFiles, $customDocFiles);
$data['action_groups'] = [];
foreach ($docFiles as $filePath) {
    $filename = pathinfo($filePath)['filename'];
    if ($filename == 'documentation') {
        // find key from filePath between tools and actions
        $matches = [];
        if (preg_match('/tools(?:\\/|\\\)([^\/]*)(?:\\/|\\\)actions(?:\\/|\\\)documentation.yaml/', $filePath, $matches)
            ||
            preg_match('/(custom)(?:\\/|\\\)actions(?:\\/|\\\)documentation.yaml/', $filePath, $matches)
            ) {
            $key = $matches[1];
        } else {
            $key = $filename;
        }
    } else {
        $key = $filename;
    }
    $data['action_groups'][$key] = Yaml::parseFile($filePath);
    // remove file for no admins if 'onlyForAdmins'
    if (isset($data['action_groups'][$key]['onlyForAdmins'])
        && $data['action_groups'][$key]['onlyForAdmins']
        && !$GLOBALS['wiki']->UserIsAdmin()) {
        unset($data['action_groups'][$key]);
    } else {
        // When order is not defined, put at the end
        if (empty($data['action_groups'][$key]['position'])) {
            $data['action_groups'][$key]['position'] = 1000;
        }
    }
}
// Sort by position
uasort($data['action_groups'], function ($a, $b) {
    return $a['position'] - $b['position'];
});

// Handle translations
function test_print(&$item, $key)
{
    if (is_string($item) && startsWith($item, '_t')) {
        preg_match("/_t\((.+)\)/", $item, $trans_key);
        $item = _t($trans_key[1]);
    }
}
array_walk_recursive($data['action_groups'], 'test_print');

// add label to catch for actionBuilderTextareaName
$data['actionBuilderTextareaName'] = $GLOBALS['wiki']->config['actionbuilder_textarea_name'] ?? '';

// ---------------
// Render Template
// ---------------
echo $this->render('@aceditor/actions-builder.tpl.html', ['data' => $data]);
