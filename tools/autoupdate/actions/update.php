<?php
namespace AutoUpdate;

$loader = require __DIR__ . '/../vendor/autoload.php';

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.js');
$this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');

// tries to give 5 minutes time for the script to execute
@ini_set('max_execution_time', 300);
@set_time_limit(300);

$autoUpdate = new AutoUpdate($this);
$messages = new Messages();
$controller = new Controller($autoUpdate, $messages, $this);

//$controller->run($_GET, $this->getParameter('filter'));
// Can't see where filter is used => getting rid of it
$requestedVersion = $this->getParameter('version');
if (isset($requestedVersion) && $requestedVersion != '') {
    $controller->run($_GET, $requestedVersion);
} else {
    $controller->run($_GET);
}
