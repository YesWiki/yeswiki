<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Kernel\Service\SsrfUrlValidator;

class HttpSignatureService
{
    protected $httpClient;
    protected $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    public function getDigest($message)
    {
        return 'SHA-256=' . base64_encode(hash('sha256', $message, true));
    }

    public function generateSignature($activity, $url, $form)
    {
        $privateKey = $form['activitypub_private_key'];

        $message = json_encode($activity, JSON_UNESCAPED_SLASHES);
        $digest = $this->getDigest($message);

        $date = gmdate("D, d M Y H:i:s \G\M\T");
        $contentType = 'application/activity+json'; // TODO allow to pass custom headers. This only works for POST requests.

        // parse_url() is `array|false` and every key of the array is optional; an inbox URL
        // without a host is not one we can sign for (ticket 40)
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || !isset($urlParts['host'])) {
            throw new \Exception("cannot sign a request for '$url': it has no host");
        }

        $sigParts = [
            '(request-target)' => 'post ' . ($urlParts['path'] ?? '/'),
            'host' => $urlParts['host'],
            'date' => $date,
            'digest' => $digest,
            'content-type' => $contentType,
        ];

        $sigSrc = join("\n", array_map(
            fn ($k, $v) => "{$k}: {$v}",
            array_keys($sigParts),
            array_values($sigParts)
        ));

        openssl_sign($sigSrc, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        $sigHeaderParts = [
            'keyId' => $activity['actor'] . '#main-key',
            'algorithm' => 'rsa-sha256',
            'headers' => join(' ', array_keys($sigParts)),
            'signature' => base64_encode($signature),
        ];

        return [
            'Date' => $date,
            'Content-Type' => $contentType,
            'Digest' => $digest,
            'Signature' => join(',', array_map(
                fn ($k, $v) => "{$k}=\"{$v}\"",
                array_keys($sigHeaderParts),
                array_values($sigHeaderParts)
            )),
        ];
    }

    public function verifySignature(Request $request)
    {
        if (!$request->headers->has('Signature')) {
            throw new \Exception('No signature');
        }

        $sigConf = parse_ini_string(
            strtr($request->headers->get('Signature'), [',' => "\n"])
        );

        if (!isset($sigConf['keyId'],$sigConf['algorithm'],$sigConf['headers'],$sigConf['signature'])) {
            throw new \Exception('Malformed signature');
        }

        $resolve = $this->ssrfUrlValidator->resolveSafe($sigConf['keyId']);

        $response = $this->httpClient->request('GET', $sigConf['keyId'], [
            'headers' => ['Accept' => 'application/ld+json'],
            'max_redirects' => 0,
            'resolve' => $resolve,
        ]);

        $actor = json_decode($response->getContent(), true);

        if (!isset($actor['publicKey'],$actor['publicKey']['id'],$actor['publicKey']['publicKeyPem'])) {
            throw new \Exception('Missing public key');
        }

        if ($sigConf['keyId'] !== $actor['publicKey']['id']) {
            throw new \Exception('Signature keyId does not match actor public key id');
        }

        $actorPublicKey = openssl_get_publickey($actor['publicKey']['publicKeyPem']);
        if (!$actorPublicKey) {
            throw new \Exception('Malformed public key');
        }

        // We cannot use getRequestUri() because it returns the real URI, eg. /?api/forms/2/actor
        $requestUri = $request->getScriptName();

        $sigParts = [];
        foreach (explode(' ', $sigConf['headers']) as $headerKey) {
            if ($headerKey === '(request-target)') {
                $sigParts[] = sprintf('%s: %s %s', $headerKey, strtolower($request->getMethod()), $requestUri);
            } else {
                if (!$request->headers->has($headerKey)) {
                    throw new \Exception('Missing signature part: ' . $headerKey);
                }
                $sigParts[] = sprintf('%s: %s', $headerKey, $request->headers->get($headerKey));
            }
        }

        if (openssl_verify(join("\n", $sigParts), base64_decode($sigConf['signature']), $actorPublicKey, strtoupper($sigConf['algorithm'])) !== 1) {
            throw new \Exception('Signature verification failed');
        }

        if ($request->headers->get('Digest') !== $this->getDigest($request->getContent())) {
            throw new \Exception('Digest mismatch');
        }
    }
}
