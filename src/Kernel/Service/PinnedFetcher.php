<?php

namespace YesWiki\Kernel\Service;

/**
 * Fetches an address the wiki was handed by someone else, checking and pinning every hop: curl never follows a redirect itself, since it would not run the check again on the new address.
 */
class PinnedFetcher
{
    public const MAX_REDIRECTS = 3;

    public function __construct(private readonly SsrfUrlValidator $validator)
    {
    }

    /**
     * The body found at $url, or an exception saying why there is none.
     *
     * @param array{schemes?: list<string>, connectTimeout?: int, timeout?: int, maxBytes?: int, maxRedirects?: int, headers?: list<string>, userAgent?: string, verifyPeer?: bool} $options
     */
    public function fetch(string $url, array $options = []): string
    {
        $body = $this->stream($url, $options);
        try {
            return (string)stream_get_contents($body);
        } finally {
            fclose($body);
        }
    }

    /**
     * The body found at $url as a rewound temporary stream the caller closes, or an exception saying why there is none.
     *
     * @param array{schemes?: list<string>, connectTimeout?: int, timeout?: int, maxBytes?: int, maxRedirects?: int, headers?: list<string>, userAgent?: string, verifyPeer?: bool} $options
     *
     * @return resource
     */
    public function stream(string $url, array $options = [])
    {
        $sink = fopen('php://temp', 'w+b');
        if ($sink === false) {
            throw new \RuntimeException('Cannot open a buffer to download into');
        }

        try {
            $this->download($url, $sink, $options);
        } catch (\Throwable $error) {
            fclose($sink);

            throw $error;
        }
        rewind($sink);

        return $sink;
    }

    /**
     * @param resource                                                                                                                                                              $sink
     * @param array{schemes?: list<string>, connectTimeout?: int, timeout?: int, maxBytes?: int, maxRedirects?: int, headers?: list<string>, userAgent?: string, verifyPeer?: bool} $options
     */
    private function download(string $url, $sink, array $options): void
    {
        $origin = self::originOf($url);
        $maxRedirects = $options['maxRedirects'] ?? self::MAX_REDIRECTS;
        for ($hop = 0;; $hop++) {
            ftruncate($sink, 0);
            rewind($sink);
            [$status, $location] = $this->request($url, $sink, $options, self::originOf($url) === $origin);
            if ($status >= 300 && $status < 400 && $location !== null && $location !== '') {
                if ($hop >= $maxRedirects) {
                    throw new \Exception("Too many redirects getting content from '{$url}'");
                }
                $url = self::absoluteUrl($url, $location);

                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new \Exception("Error getting content from '{$url}' (HTTP {$status})");
            }
            if (ftell($sink) === 0) {
                throw new \Exception("Nothing to get from '{$url}'");
            }

            return;
        }
    }

    /**
     * One request, pinned to the address that was checked; the request headers only travel to the origin first asked.
     *
     * @param resource                                                                                                                                                              $sink
     * @param array{schemes?: list<string>, connectTimeout?: int, timeout?: int, maxBytes?: int, maxRedirects?: int, headers?: list<string>, userAgent?: string, verifyPeer?: bool} $options
     *
     * @return array{0: int, 1: ?string} the status and the Location header, if any
     */
    private function request(string $url, $sink, array $options, bool $sameOrigin): array
    {
        $pin = $this->validator->curlPin($url, $options['schemes'] ?? ['http', 'https']);
        $handle = curl_init($url);
        if ($handle === false) {
            throw new \Exception("Cannot fetch '{$url}'");
        }
        $maxBytes = $options['maxBytes'] ?? 0;
        $location = null;
        $written = 0;
        curl_setopt_array($handle, $pin + [
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $options['connectTimeout'] ?? 5,
            CURLOPT_TIMEOUT => $options['timeout'] ?? 10,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$location): int {
                if (stripos($line, 'location:') === 0) {
                    $location = trim(substr($line, 9));
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($sink, $maxBytes, &$written): int {
                $written += strlen($chunk);
                if ($maxBytes > 0 && $written > $maxBytes) {
                    return 0;
                }

                return (int)fwrite($sink, $chunk);
            },
        ]);
        if (!empty($options['userAgent'])) {
            curl_setopt($handle, CURLOPT_USERAGENT, $options['userAgent']);
        }
        if ($sameOrigin && !empty($options['headers'])) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $options['headers']);
        }
        if (($options['verifyPeer'] ?? true) === false) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        }
        curl_exec($handle);
        $error = curl_errno($handle);
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80500) {
            curl_close($handle);
        }
        if ($error) {
            throw new \Exception("Error getting content from '{$url}' (" . curl_strerror($error) . ')');
        }

        return [$status, $location];
    }

    /** scheme://host:port of $url, or null when it has none. */
    private static function originOf(string $url): ?string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /** A Location header may be relative to the address it came from. */
    private static function absoluteUrl(string $currentUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $parts = parse_url($currentUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return $parts['scheme'] . ':' . $location;
        }
        $root = $parts['scheme'] . '://' . $parts['host'] . (empty($parts['port']) ? '' : ':' . $parts['port']);
        if (str_starts_with($location, '/')) {
            return $root . $location;
        }
        $path = empty($parts['path']) ? '/' : $parts['path'];

        return $root . substr($path, 0, (int)strrpos($path, '/') + 1) . $location;
    }
}
