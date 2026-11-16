<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\HttpFoundation\Response;

/** The error page for when the wiki cannot draw its layout: a whole document with its own styles, in French and English (end-users 15). */
#[Exclude]
final class ErrorFallbackPage
{
    public const TEMPLATE = __DIR__ . '/../../../templates/error-fallback.html';

    public static function response(int $status, ?string $incident, string $home): Response
    {
        $page = (string)@file_get_contents(self::TEMPLATE);
        if ($incident === null) {
            $page = (string)preg_replace('/\s*<p[^>]*data-incident[^>]*>.*?<\/p>/s', '', $page);
        }
        $page = strtr($page, [
            '%status%' => (string)$status,
            '%incident%' => htmlspecialchars((string)$incident),
            '%home%' => htmlspecialchars($home === '' ? '/' : $home),
        ]);

        return new Response($page !== '' ? $page : 'Error ' . $status, $status, ['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
