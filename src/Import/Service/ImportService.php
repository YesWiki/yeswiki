<?php

namespace YesWiki\Import\Service;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Exception\CurlTimeoutException;
use YesWiki\Kernel\Service\SsrfUrlValidator;

class ImportService
{
    public const SCHEMES = ['http', 'https'];
    private const MAX_REDIRECTS = 5;

    public function __construct(private readonly Storage $storage, private readonly LocalFiles $localFiles, private readonly SsrfUrlValidator $ssrfUrlValidator)
    {
    }

    /**
     * extract baseUrl and rootPage for external url TODO check if this function should be in UrlService after refactor.
     *
     * @return array{}|array{string, string, bool} [$baseUrl,$rootPage,$rewriteModeEnabled], empty when the url names no wiki page
     */
    public function extractBaseUrlAndRootPage(string $inputUrl): array
    {
        $redirectedInputUrl = $this->retrieveUrlAfterRedirect($inputUrl);

        $extraction = $this->extractBaseUrlModeAndTag($redirectedInputUrl);
        if (empty($extraction)) {
            return [];
        }
        list($baseUrl, $rewriteModeEnabled, $tag) = $extraction;
        $redirectedRootUrl = $this->retrieveUrlAfterRedirect($baseUrl . '/');
        $extraction = $this->extractBaseUrlModeAndTag($redirectedRootUrl);
        if (empty($extraction)) {
            return [];
        }
        list($baseUrl, $rewriteModeEnabled, $rootPage) = $extraction;

        return [$baseUrl, $rootPage, $rewriteModeEnabled];
    }

    /**
     * extract baseUrl, rewriteModeEnabled and tag TODO check if this function should be in UrlService after refactor.
     *
     * @return array{}|array{string, bool, string} [$baseUrl, $rewriteModeEnabled, $tag], empty when the url names no wiki page
     */
    private function extractBaseUrlModeAndTag(string $inputUrl): array
    {
        $rewriteModeEnabled = null;
        if (preg_match('/wiki=(' . WN_CAMEL_CASE_EVOLVED . ')/u', $inputUrl, $matches)) {
            $tag = $matches[1];
            if (preg_match('/(.*)\/wakka.php\?.*wiki=' . $tag . '/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = false;
                $baseUrl = $matches[1];
            } elseif (preg_match('/(.*)\/\?.*wiki=' . $tag . '/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = false;
                $baseUrl = $matches[1];
            } elseif (preg_match('/(.*)\/[^\/]*wiki=' . $tag . '/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = true;
                $baseUrl = $matches[1];
            }
        } elseif (preg_match('/(.*)\/wakka.php\?(' . WN_CAMEL_CASE_EVOLVED . ')/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = false;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        } elseif (preg_match('/(.*)\/\?(' . WN_CAMEL_CASE_EVOLVED . ')/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = false;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        } elseif (preg_match('/(https?:\/\/(?:localhost|[0-9]{3}:[0-9]{3}:[0-9]{3}:[0-9]{3}|(?:[^\/]*\.[a-z]{3})).*)\/(' . WN_CAMEL_CASE_EVOLVED . ')(?:\/)?$/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = true;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        }
        if (empty($baseUrl) || is_null($rewriteModeEnabled) || empty($tag)) {
            return [];
        }

        return [$baseUrl, $rewriteModeEnabled, $tag];
    }

    /**
     * retrieve url after redirection TODO check if this function should be in UrlService after refactor.
     *
     * @return string $outputUrl
     */
    private function retrieveUrlAfterRedirect(string $inputUrl): string
    {
        // each hop is followed here rather than by curl, so that every address is checked in turn
        $outputUrl = $inputUrl;
        for ($hop = 0; $hop < self::MAX_REDIRECTS; ++$hop) {
            try {
                $headers = $this->getHeaders($outputUrl);
            } catch (CurlTimeoutException $th) {
                return $outputUrl;
            }
            $location = !empty($headers['Location'])
                ? $headers['Location']
                : (
                    !empty($headers['location'])
                    ? $headers['location']
                    : ''
                );

            if (empty($location)) {
                return $outputUrl;
            }
            $outputUrl = $this->absoluteUrl(
                $outputUrl,
                is_array($location) ? $location[count($location) - 1] : $location
            );
        }

        return $outputUrl;
    }

    /** A Location header may be relative to the address it came from. */
    private function absoluteUrl(string $currentUrl, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }
        $parts = parse_url($currentUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return $location;
        }
        $root = $parts['scheme'] . '://' . $parts['host'] . (empty($parts['port']) ? '' : ':' . $parts['port']);
        if (strpos($location, '/') === 0) {
            return $root . $location;
        }
        $path = empty($parts['path']) ? '/' : $parts['path'];

        return $root . substr($path, 0, (int)strrpos($path, '/') + 1) . $location;
    }

    /**
     * The `@return string` this used to carry contradicted the native `array` right below it.
     *
     * @return array<int|string, string|list<string>> the response headers, a header seen more
     *                                                than once holding every value it was sent with
     *
     * @throws \Exception
     * @throws CurlTimeoutException
     */
    private function getHeaders(string $url): array
    {
        // Two scratch files, and neither ever becomes the wiki's: curl writes the body into one
        // and the headers into the other, and only the headers are read back. Storage owns the
        // making and the removing, including when this throws part-way through.
        $pin = $this->ssrfUrlValidator->curlPin($url, self::SCHEMES);
        [$error, $content] = $this->storage->withTemporaryFile('body', fn (string $bodyPath) => $this->storage->withTemporaryFile('headers', function (string $headerPath) use ($url, $bodyPath, $pin) {
            $body = $this->localFiles->openForWriting($bodyPath);
            $headers = $this->localFiles->openForWriting($headerPath);
            if ($body === null || $headers === null) {
                throw new \Exception("Error getting content from $url (temporary file could not be opened)");
            }

            $ch = curl_init($url);
            foreach ($pin as $option => $optionValue) {
                curl_setopt($ch, $option, $optionValue);
            }
            curl_setopt($ch, CURLOPT_FILE, $body);
            curl_setopt($ch, CURLOPT_WRITEHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, true);
            // a redirect is followed by the caller, which checks the new address first
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_exec($ch);
            $failed = curl_errno($ch);
            curl_close($ch);
            fclose($body);
            fclose($headers);

            return [$failed, $failed ? '' : $this->storage->readForeign($headerPath)];
        }));
        if ($error) {
            $errorStr = curl_strerror($error);
            if (in_array($error, [12, 28])) {
                throw new CurlTimeoutException("Error getting content from $url ($errorStr)");
            }
            throw new \Exception("Error getting content from $url ($errorStr)");
        }
        $intermediate = empty($content) ? [] : array_filter(array_map('trim', explode("\n", $content)));
        $output = [];
        foreach ($intermediate as $header) {
            if (strpos($header, ':') === false) {
                $output[] = $header;
            } else {
                list($header, $value) = explode(':', $header, 2);
                $value = trim($value);
                if (!isset($output[$header])) {
                    $output[$header] = $value;
                } elseif (is_string($output[$header])) {
                    $output[$header] = [
                        $output[$header],
                        $value,
                    ];
                } else {
                    $output[$header][] = $value;
                }
            }
        }

        return $output;
    }
}
