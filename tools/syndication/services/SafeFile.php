<?php

namespace YesWiki\Syndication\Service;

use SimplePie\File;
use YesWiki\Bazar\Service\SsrfUrlValidator;

/**
 * The file SimplePie fetches a feed through, refusing any address the wiki must not reach.
 *
 * SimplePie follows a redirect by calling __construct() again on the same object, so every hop
 * lands here and is checked in turn; the address that was checked is then pinned for curl, which
 * would otherwise resolve the name a second time.
 */
class SafeFile extends File
{
    public const SCHEMES = ['http', 'https'];

    public static ?SsrfUrlValidator $validator = null;

    public function __construct($url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false, $curl_options = [])
    {
        $curl_options = array_replace($curl_options, self::pin($url));

        parent::__construct($url, $timeout, $redirects, $headers, $useragent, $force_fsockopen, $curl_options);
    }

    /**
     * @return array<int,mixed> curl options tying the request to the checked address
     */
    public static function pin(string $url): array
    {
        $resolved = (self::$validator ?? new SsrfUrlValidator())->resolveSafe($url, self::SCHEMES);
        $host = array_key_first($resolved);
        $parts = parse_url($url);
        $port = $parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80);

        return [
            CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolved[$host]}"],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }
}
