<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\HttpFoundation\Request;

/** Which proxies the wiki believes about the client's address and scheme, from `trusted_proxies` and `trusted_headers` (replicas 07). */
final class TrustedProxies
{
    public const HEADERS = [
        'x-forwarded-for' => Request::HEADER_X_FORWARDED_FOR,
        'x-forwarded-proto' => Request::HEADER_X_FORWARDED_PROTO,
        'x-forwarded-host' => Request::HEADER_X_FORWARDED_HOST,
        'x-forwarded-port' => Request::HEADER_X_FORWARDED_PORT,
        'x-forwarded-prefix' => Request::HEADER_X_FORWARDED_PREFIX,
        'forwarded' => Request::HEADER_FORWARDED,
    ];

    public const DEFAULT_HEADERS = ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-host', 'x-forwarded-port'];

    /**
     * Tells Symfony's Request whom to believe, before any request is built; with no proxy named, nobody is.
     *
     * @param array<string, mixed> $config
     */
    public static function apply(array $config): void
    {
        $proxies = self::listOf($config['trusted_proxies'] ?? null);
        if ($proxies === []) {
            Request::setTrustedProxies([], 0);

            return;
        }
        $named = self::listOf($config['trusted_headers'] ?? null);
        $headers = 0;
        foreach ($named === [] ? self::DEFAULT_HEADERS : $named as $header) {
            $headers |= self::HEADERS[strtolower($header)] ?? throw new \InvalidArgumentException("trusted_headers names '{$header}', which is none of " . implode(', ', array_keys(self::HEADERS)));
        }
        if ($headers < 1 || $headers > 63) {
            throw new \LogicException("trusted_headers name no header Symfony knows ({$headers})");
        }
        Request::setTrustedProxies($proxies, $headers);
    }

    /** @return list<string> a setting given as a list, or as one string with commas */
    private static function listOf(mixed $setting): array
    {
        $items = is_array($setting) ? $setting : explode(',', is_scalar($setting) ? (string)$setting : '');

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim(is_scalar($item) ? (string)$item : ''), $items), static fn (string $item): bool => $item !== ''));
    }
}
