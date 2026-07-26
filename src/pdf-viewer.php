<?php

// ticket 17: relocated from tools/attach/libs/pdf-viewer.php. Copied by
// ComposerScriptsHelper into javascripts/vendor/pdfjs-dist/web/ whenever pdf.js is
// (re)installed -- pdf.js's own viewer.html doesn't set a permissive
// frame-ancestors CSP, which PdfAction's embedded <iframe> needs.

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
    exit;
}
