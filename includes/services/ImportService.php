<?php

namespace YesWiki\Core\Service;

use Exception;
use YesWiki\Core\Exception\CurlTimeoutException;

// use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
// use YesWiki\Wiki;

class ImportService
{
    // protected $wiki;
    // protected $params;

    public function __construct(/*Wiki $wiki, ParameterBagInterface $params*/)
    {
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
        try {
            $headers = $this->getHeaders($inputUrl);
        } catch (CurlTimeoutException $th) {
            return $intputUrl ?? '';
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
     * @param string $url
     *
     * @return string
     *
     * @throws Exception
     * @throws CurlTimeoutException
     */
    private function getHeaders($url): array
    {
        $destPath = tempnam('cache', 'tmp_to_delete_');
        $destPathHeaders = tempnam('cache', 'tmp_headers_to_delete_');
        $fp = fopen($destPath, 'wb');
        $fph = fopen($destPathHeaders, 'wb');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_WRITEHEADER, $fph);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
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
