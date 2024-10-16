<?php

define('VIEWER_PATH', './viewer.html');
if (file_exists(VIEWER_PATH)) {
    // allow local ('self') and everyone (*)
    header("Content-Security-Policy: frame-ancestors 'self' *;");
    header('Content-Type: text/html');
    readfile(VIEWER_PATH);
} else {
    header('HTTP/1.0 404 Not found');
    header('Content-Type: text/html');
    echo <<<HTML
    <!DOCTYPE html>
    <html>
        <head></head>
        <body>
            <h1>Error 404 Not found</h1>
        </body>
    </html>
    HTML;
    exit();
}
