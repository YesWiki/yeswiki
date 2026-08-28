<?php

namespace YesWiki\Content\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use YesWiki\Kernel\Service\SsrfUrlValidator;
use YesWiki\Kernel\Service\StringUtilService;

/** Ask a batch of URLs whether they still answer, without downloading what they hold. */
class UrlReachability
{
    public const MAX_URLS = 200;

    public const NOT_HTTPS = 'not_https';
    public const BLOCKED = 'blocked';
    public const OVER_LIMIT = 'over_limit';

    private const TIMEOUT = 5;
    private const MAX_DURATION = 15;

    protected SsrfUrlValidator $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    /**
     * @param list<string> $urls
     *
     * @return array<string, array{fetched: bool, reason?: string, status?: int|null, error?: string|null}> one entry per url, keyed by url
     */
    public function probe(array $urls): array
    {
        $urls = array_values(array_unique($urls));
        $results = [];

        foreach (array_slice($urls, self::MAX_URLS) as $url) {
            $results[$url] = ['fetched' => false, 'reason' => self::OVER_LIMIT];
        }

        $requests = [];
        foreach (array_slice($urls, 0, self::MAX_URLS) as $url) {
            if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $results[$url] = ['fetched' => false, 'reason' => self::NOT_HTTPS];
                continue;
            }
            $encoded = StringUtilService::encodeUrlNonAscii($url);
            try {
                $requests[$url] = ['url' => $encoded, 'pin' => $this->ssrfUrlValidator->resolveSafe($encoded)];
            } catch (\Throwable $error) {
                $results[$url] = ['fetched' => false, 'reason' => self::BLOCKED];
            }
        }

        return $results + $this->head($requests);
    }

    /**
     * @param array<string, array{url: string, pin: array<string, string>}> $requests
     *
     * @return array<string, array{fetched: bool, status: int|null, error: string|null}>
     */
    private function head(array $requests): array
    {
        if (empty($requests)) {
            return [];
        }

        $client = HttpClient::create();
        $responses = [];
        foreach ($requests as $url => $request) {
            $responses[$url] = $client->request('HEAD', $request['url'], $this->options($request['pin']));
        }

        $results = [];
        foreach ($responses as $url => $response) {
            try {
                $status = $response->getStatusCode();
                if (in_array($status, [405, 501], true)) {
                    $retry = $client->request('GET', $requests[$url]['url'], $this->options($requests[$url]['pin']));
                    $status = $retry->getStatusCode();
                }
                $results[$url] = ['fetched' => true, 'status' => $status, 'error' => null];
            } catch (ExceptionInterface $error) {
                $results[$url] = ['fetched' => true, 'status' => null, 'error' => $error->getMessage()];
            }
        }

        return $results;
    }

    /**
     * @param array<string, string> $pin
     *
     * @return array<string, mixed>
     */
    private function options(array $pin): array
    {
        return [
            'resolve' => $pin,
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
            'headers' => ['User-Agent' => 'YesWiki link check'],
        ];
    }
}
