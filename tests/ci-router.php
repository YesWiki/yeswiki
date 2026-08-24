<?php

/**
 * Router for PHP's built-in server, which honours no .htaccess and would otherwise serve
 * private/ to anyone. docker/nginx.conf and the binary's Caddyfile both deny it; CI has to
 * as well, or ArchiveService correctly refuses to archive a wiki whose backups are public.
 */
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

if (preg_match('#/(.*/)?private/#i', $path) === 1) {
    http_response_code(403);

    return true;
}

return false;
