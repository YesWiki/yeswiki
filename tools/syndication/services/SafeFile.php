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
        return (self::$validator ?? new SsrfUrlValidator())->curlPin($url, self::SCHEMES);
    }
}
