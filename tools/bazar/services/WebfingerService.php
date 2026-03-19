<?php

namespace YesWiki\Bazar\Service;

use Exception;
use YesWiki\Bazar\Service\WebFinger;
use Symfony\Component\HttpClient\HttpClient;

class WebfingerService
{
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
    }

    public function splitHandle($handle) {
        if (!preg_match(
                '/^@?(?P<user>[\w\-\.]+)@(?P<host>[\w\.\-]+)(?P<port>:[\d]+)?$/',
                $handle,
                $matches
            )
        ) {
            throw new Exception(
                "WebFinger handle is malformed '{$handle}'"
            );
        }

        return $matches;
    }

    public function formatLocalActor($handle, $actorUri) {
        $webfinger = new WebFinger();

        $webfinger->setSubject($handle);
        $webfinger->setAliases([$actorUri]);
        $webfinger->setLinks([
            [
                "rel" => "self",
                "type" => "application/activity+json",
                "href" => $actorUri,
            ]
        ]);

        return ($webfinger->toArray());
    }

    public function getRemoteActor($handle) {
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

        $response = $this->httpClient->request('GET', $webfingerUrl, [
            'headers' => [
                'Accept' => 'application/json'
            ]
        ]);

        $json = (array) json_decode($response->getContent(), true);

        $webfinger = new WebFinger($json);

        return $webfinger->getProfileId();
    }
}
