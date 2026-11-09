<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\HttpClient\HttpClient;

class WebfingerService
{
    protected $httpClient;
    protected $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    public function splitHandle($handle)
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

    public function formatLocalActor($handle, $actorUri, ?string $profileUrl = null)
    {
        $webfinger = new WebFinger();

        $webfinger->setSubject('acct:' . ltrim($handle, '@'));
        $webfinger->setAliases([$actorUri]);
        $links = [
            [
                'rel' => 'self',
                'type' => 'application/activity+json',
                'href' => $actorUri,
            ],
        ];
        if (!empty($profileUrl)) {
            $links[] = [
                'rel' => 'http://webfinger.net/rel/profile-page',
                'type' => 'text/html',
                'href' => $profileUrl,
            ];
        }
        $webfinger->setLinks($links);

        return $webfinger->toArray();
    }

    protected function getWebfingerObject($handle)
    {
        $matches = $this->splitHandle($handle);

        // Unformat Mastodon handle @user@host => user@host
        $handle = strpos($handle, '@') === 0
            ? substr($handle, 1) : $handle;

        // Build a WebFinger URL
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
            'timeout' => HttpSignatureService::TIMEOUT,
            'max_duration' => HttpSignatureService::MAX_DURATION,
            'resolve' => $resolve,
        ]);

        $json = (array)json_decode($response->getContent(), true);

        return new WebFinger($json);
    }

    public function getRemoteActor($handle)
    {
        $webfinger = $this->getWebfingerObject($handle);

        return $webfinger->getProfileId();
    }

    public function getInteractionUrl($handle, $actorToFollow)
    {
        $webfinger = $this->getWebfingerObject($handle);

        $interactionUrl = $webfinger->getInteractionUrl();
        if (empty($interactionUrl)) {
            return null;
        }

        return str_replace('{uri}', urlencode($actorToFollow), $interactionUrl);
    }
}
