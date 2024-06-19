<?php

// Mime type pour le SVG
header('Content-type: image/svg+xml');

if (isset($_GET['svg'])) {
    $svg = $_GET['svg'];
} else {
    $svg = 'reseaupagecourante';
}
if (preg_match('/^[a-z0-9]*$/', $svg)) {
    $url = 'handlers/page/svg/' . $svg . '.php';
    if (is_file($url)) {
        include $url;
    }
}
