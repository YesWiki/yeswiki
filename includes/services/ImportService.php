<?php

namespace YesWiki\Core\Service;

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
     * @param string $inputUrl
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
        $redirectedRootUrl = $this->retrieveUrlAfterRedirect($baseUrl.'/');
        $extraction = $this->extractBaseUrlModeAndTag($redirectedInputUrl);
        if (empty($extraction)) {
            return [];
        }
        list($baseUrl, $rewriteModeEnabled, $rootPage) = $extraction;
        return [$baseUrl,$rootPage,$rewriteModeEnabled];
    }

    /**
     * extract baseUrl, rewriteModeEnabled and tag
     * @param string $inputUrl
     * @return array [$baseUrl, $rewriteModeEnabled, $tag]
     */
    private function extractBaseUrlModeAndTag($inputUrl): array
    {
        if (preg_match('/wiki=('.WN_CAMEL_CASE_EVOLVED.')/u', $inputUrl, $matches)) {
            $tag = $matches[1];
            if (preg_match('/(.*)\/wakka.php\?.*wiki='.$tag.'/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = false;
                $baseUrl = $matches[1];
            } elseif (preg_match('/(.*)\/\?.*wiki='.$tag.'/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = false;
                $baseUrl = $matches[1];
            } elseif (preg_match('/(.*)\/[^\/]*wiki='.$tag.'/u', $inputUrl, $matches)) {
                $rewriteModeEnabled = true;
                $baseUrl = $matches[1];
            }
        } elseif (preg_match('/(.*)\/wakka.php\?('.WN_CAMEL_CASE_EVOLVED.')/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = false;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        } elseif (preg_match('/(.*)\/\?('.WN_CAMEL_CASE_EVOLVED.')/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = false;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        } elseif (preg_match('/(https?:\/\/(?:localhost|[0-9]{3}:[0-9]{3}:[0-9]{3}:[0-9]{3}|(?:[^\/]*\.[a-z]{3})).*)\/('.WN_CAMEL_CASE_EVOLVED.')(?:\/)?$/u', $inputUrl, $matches)) {
            $rewriteModeEnabled = true;
            $tag = $matches[2];
            $baseUrl = $matches[1];
        }
        if (empty($baseUrl) || is_null($rewriteModeEnabled) || empty($tag)) {
            return [];
        } else {
            return [$baseUrl,$rewriteModeEnabled,$tag];
        }
    }

    /**
     * retrieve url after redirection
     * @param string $inputUrl
     * @return string $outputUrl
     */
    private function retrieveUrlAfterRedirect(string $inputUrl): string
    {
        $headers = get_headers($inputUrl, true);
        $outputUrl = $inputUrl;
        if (!empty($headers['Location'])) {
            if (is_array($headers['Location'])) {
                $outputUrl = $headers['Location'][count($headers['Location'])-1];
            } elseif (is_string($headers['Location'])) {
                $outputUrl = $headers['Location'];
            }
        }
        return $outputUrl;
    }
}
