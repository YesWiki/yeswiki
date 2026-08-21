<?php

namespace YesWiki\Import\Service;

use YesWiki\Kernel\Exception\CurlTimeoutException;

class ImportService
{
    public function __construct()
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
        try {
            $headers = $this->getHeaders($inputUrl);
        } catch (CurlTimeoutException $th) {
            // `$intputUrl` -- a typo for the parameter, so a timed-out HEAD returned the empty
            // string and the import failed with "no wiki here" instead of trying the url as given
            return $inputUrl;
        }
        $outputUrl = $inputUrl;
        $location = !empty($headers['Location'])
            ? $headers['Location']
            : (
                !empty($headers['location'])
                ? $headers['location']
                : ''
            );

        if (!empty($location)) {
            if (is_array($location)) {
                $outputUrl = $location[count($location) - 1];
            } elseif (is_string($location)) {
                $outputUrl = $location;
            }
        }

        return $outputUrl;
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
        $destPath = tempnam('cache', 'tmp_to_delete_');
        $destPathHeaders = tempnam('cache', 'tmp_headers_to_delete_');
        if ($destPath === false || $destPathHeaders === false) {
            throw new \Exception("Error getting content from $url (no temporary file could be created in cache/)");
        }
        $fp = fopen($destPath, 'wb');
        $fph = fopen($destPathHeaders, 'wb');
        if ($fp === false || $fph === false) {
            // curl reads a `false` handle as "write to stdout": the response body and its
            // headers used to be printed into the page instead of captured
            throw new \Exception("Error getting content from $url (temporary file could not be opened)");
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $fph);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        fclose($fp);
        fclose($fph);
        $content = '';
        if (!$error && file_exists($destPathHeaders)) {
            $content = (string)file_get_contents($destPathHeaders);
        }
        unlink($destPath);
        unlink($destPathHeaders);
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
