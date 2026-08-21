<?php

namespace YesWiki\Core\Service;

use Exception;
use YesWiki\Bazar\Service\SsrfUrlValidator;
use YesWiki\Core\Exception\CurlTimeoutException;

// use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
// use YesWiki\Wiki;

class ImportService
{
    public const SCHEMES = ['http', 'https'];
    private const MAX_REDIRECTS = 5;

    // protected $wiki;
    // protected $params;

    protected $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator/*, Wiki $wiki, ParameterBagInterface $params*/)
    {
        $this->ssrfUrlValidator = $ssrfUrlValidator;
        // $this->wiki = $wiki;
        // $this->params = $params;
    }

    /**
     * extract baseUrl and rootPage for external url
     * TODO check if this function should be in UrlService after refactor.
     *
     * @return array [$baseUrl,$rootPage,$rewriteModeEnabled]
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
     * extract baseUrl, rewriteModeEnabled and tag
     * TODO check if this function should be in UrlService after refactor.
     *
     * @param string $inputUrl
     *
     * @return array [$baseUrl, $rewriteModeEnabled, $tag]
     */
    private function extractBaseUrlModeAndTag($inputUrl): array
    {
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
        } else {
            return [$baseUrl, $rewriteModeEnabled, $tag];
        }
    }

    /**
     * retrieve url after redirection
     * TODO check if this function should be in UrlService after refactor.
     *
     * @return string $outputUrl
     */
    private function retrieveUrlAfterRedirect(string $inputUrl): string
    {
        // each hop is followed here rather than by curl, so that every address is checked in turn
        $outputUrl = $inputUrl;
        for ($hop = 0; $hop < self::MAX_REDIRECTS; $hop++) {
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
            if (is_array($location)) {
                $location = $location[count($location) - 1];
            }
            if (!is_string($location)) {
                return $outputUrl;
            }
            $outputUrl = $this->absoluteUrl($outputUrl, $location);
        }

        return $outputUrl;
    }

    /**
     * A Location header may be relative to the address it came from.
     */
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

        return $root . substr($path, 0, strrpos($path, '/') + 1) . $location;
    }

    /**
     * @param string $url
     *
     * @return string
     *
     * @throws Exception
     * @throws CurlTimeoutException
     */
    private function getHeaders($url): array
    {
        $pin = $this->ssrfUrlValidator->curlPin($url, self::SCHEMES);
        $destPath = tempnam('cache', 'tmp_to_delete_');
        $destPathHeaders = tempnam('cache', 'tmp_headers_to_delete_');
        $fp = fopen($destPath, 'wb');
        $fph = fopen($destPathHeaders, 'wb');
        $ch = curl_init($url);
        foreach ($pin as $option => $optionValue) {
            curl_setopt($ch, $option, $optionValue);
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $fph);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        // a redirect is followed by the caller, which checks the new address first
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // connect timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 6); // total timeout in seconds
        curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);
        fclose($fp);
        fclose($fph);
        if (!$error && file_exists($destPathHeaders)) {
            $content = file_get_contents($destPathHeaders);
        }
        unlink($destPath);
        unlink($destPathHeaders);
        if ($error) {
            $errorStr = curl_strerror($error);
            if (in_array($error, [12, 28])) {
                throw new CurlTimeoutException("Error getting content from $url ($errorStr)");
            } else {
                throw new Exception("Error getting content from $url ($errorStr)");
            }
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
                } elseif (is_array($output[$header])) {
                    $output[$header][] = $value;
                } else {
                    $output[$header] = $value;
                }
            }
        }

        return $output;
    }
}
