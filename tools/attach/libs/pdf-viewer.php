<?php

// File not included in pdfjs package
// File from YesWiki
// Use a different LICENSE file (see LICENSE at root folder)
// source file copied here via composer : tools/attach/libs/pdf-viewer.php

if (file_exists('./viewer.html')) {
    // allow local ('self') and everyone (*)
    header("Content-Security-Policy: frame-ancestors 'self' *;");
    header('Content-Type: text/html');
    readfile('./viewer.html');
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
