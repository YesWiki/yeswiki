<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Kernel\Service\SsrfUrlValidator;

class WebfingerService
{
    protected HttpClientInterface $httpClient;
    protected SsrfUrlValidator $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    /**
     * @return array<int|string, string> the preg_match captures, keyed by group name and number
     */
    public function splitHandle(string $handle): array
    {
        if (!preg_match(
            '/^@?(?P<user>[\w\-\.]+)@(?P<host>[\w\.\-]+)(?P<port>:[\d]+)?$/',
            $handle,
            $matches
        )
        ) {
            throw new \Exception("WebFinger handle is malformed '{$handle}'");
        }

        return $matches;
    }

    /**
     * @return array<string, mixed> the WebFinger response body for a local actor
     */
    public function formatLocalActor(string $handle, string $actorUri): array
    {
        $webfinger = new WebFinger();

        $webfinger->setSubject($handle);
        $webfinger->setAliases([$actorUri]);
        $webfinger->setLinks([
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $actorUri,
            ],
        ]);

        return $webfinger->toArray();
    }

    protected function getWebfingerObject(string $handle): WebFinger
    {
        $matches = $this->splitHandle($handle);

        $handle = strpos($handle, '@') === 0
            ? substr($handle, 1) : $handle;

        $webfingerUrl = sprintf(
            '%s://%s%s/.well-known/webfinger?resource=acct:%s',
            'https',
            $matches['host'],
            isset($matches['port']) ? $matches['port'] : '',
            $handle
        );

        $resolve = $this->ssrfUrlValidator->resolveSafe($webfingerUrl);

        $response = $this->httpClient->request('GET', $webfingerUrl, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'max_redirects' => 0,
            'resolve' => $resolve,
        ]);

        $json = (array)json_decode($response->getContent(), true);

        return new WebFinger($json);
    }

    /** @return string|null the remote profile id, or null when the response declares no self link */
    public function getRemoteActor(string $handle): ?string
    {
        $webfinger = $this->getWebfingerObject($handle);

        return $webfinger->getProfileId();
    }

    public function getInteractionUrl(string $handle, string $actorToFollow): string
    {
        $webfinger = $this->getWebfingerObject($handle);

        $interactionUrl = $webfinger->getInteractionUrl();
        if ($interactionUrl === null) {
            throw new \Exception("the remote actor '$handle' publishes no subscribe template");
        }

        return str_replace('{uri}', urlencode($actorToFollow), $interactionUrl);
    }
}
