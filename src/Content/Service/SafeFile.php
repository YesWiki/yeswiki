<?php

namespace YesWiki\Content\Service;

use SimplePie\File;
use YesWiki\Kernel\Service\SsrfUrlValidator;

/**
 * The file SimplePie fetches a feed through, refusing any address the wiki must not reach.
 *
 * SimplePie follows a redirect by calling __construct() again on the same object, so every hop lands here and is checked in turn; the address that was checked is then pinned for curl, which would otherwise resolve the name a second time.
 */
class SafeFile extends File
{
    public const SCHEMES = ['http', 'https'];

    public static ?SsrfUrlValidator $validator = null;

    /**
     * @param array<string, string>|null $headers
     * @param string|null                $useragent
     * @param array<int, mixed>          $curl_options
     */
    public function __construct(string $url, int $timeout = 10, int $redirects = 5, ?array $headers = null, ?string $useragent = null, bool $force_fsockopen = false, array $curl_options = [])
    {
        $curl_options = array_replace($curl_options, self::pin($url));

        parent::__construct($url, $timeout, $redirects, $headers, $useragent, $force_fsockopen, $curl_options);
    }

    /**
     * @return array<int,mixed> curl options tying the request to the checked address, and nothing at all for a file already on disk -- SimplePie reads a local feed through this same class, and there is no address to check there. A string that merely fails to parse as a url is not a local file, and still goes through the check.
     */
    public static function pin(string $url): array
    {
        if (parse_url($url, PHP_URL_SCHEME) === null && is_file($url)) {
            return [];
        }

        return (self::$validator ?? new SsrfUrlValidator())->curlPin($url, self::SCHEMES);
    }
}
