<?php
namespace AutoUpdate;

$loader = require __DIR__ . '/../vendor/autoload.php';

if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

$autoUpdate = new AutoUpdate($this);
$messages = new Messages();
$controller = new Controller($autoUpdate, $messages);

$controller->run($_GET, $this->getParameter('filter'));
