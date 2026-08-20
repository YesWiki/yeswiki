<?php

namespace YesWiki\Bazar\Service;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;

class HttpSignatureService
{
    protected $httpClient;
    protected $ssrfUrlValidator;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator)
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
    }

    public function generateKeyPair()
    {
        $result = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($result, $privKey);

        $pubKey = openssl_pkey_get_details($result);
        $pubKey = $pubKey["key"];

        return [$privKey, $pubKey];
    }

    public function getDigest($message) {
        return 'SHA-256=' . base64_encode(hash('sha256', $message, true));
    }
    
    public function generateSignature($activity, $url, $form) {
        $privateKey = $form['bn_activitypub_private_key'];

        $message = json_encode($activity, JSON_UNESCAPED_SLASHES);
        $digest = $this->getDigest($message);

        $date = gmdate("D, d M Y H:i:s \G\M\T");
        $contentType = 'application/activity+json'; // TODO allow to pass custom headers. This only works for POST requests.

        $urlParts = parse_url($url);

        $sigParts = [
            '(request-target)' => 'post ' . $urlParts['path'],
            'host' => $urlParts['host'],
            'date' => $date,
            'digest' => $digest,
            'content-type' => $contentType,
        ];

        $sigSrc = join("\n", array_map(
            fn($k, $v) => "{$k}: {$v}",
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
                    fn($k, $v) => "{$k}=\"{$v}\"",
                    array_keys($sigHeaderParts),
                    array_values($sigHeaderParts)
                )),
        ];
    }


    /**
     * Check the request really was signed by the key it names, and say whose key it was.
     *
     * A valid signature only answers "was this signed"; the caller still has to ask "by whom",
     * so the owner of the key is returned for it to bind the activity to.
     *
     * @return string the actor the verified key belongs to
     *
     * @throws Exception when anything about the signature does not hold
     */
    public function verifySignature(Request $request): string {
        if (!$request->headers->has('Signature')) {
            throw new Exception('No signature');
        }

        $sigConf = parse_ini_string(
            strtr($request->headers->get('Signature'), ["," => "\n"])
        );

        if (!isset($sigConf['keyId'],$sigConf['algorithm'],$sigConf['headers'],$sigConf['signature'])) {
            throw new Exception('Malformed signature');
        }

        $resolve = $this->ssrfUrlValidator->resolveSafe($sigConf['keyId']);

        $response = $this->httpClient->request('GET', $sigConf['keyId'], [
            'headers' => [ 'Accept' => 'application/ld+json'],
            'max_redirects' => 0,
            'resolve' => $resolve,
        ]);

        $actor = json_decode($response->getContent(), true);
        
        if (!isset($actor['publicKey'],$actor['publicKey']['id'],$actor['publicKey']['publicKeyPem'])) {
            throw new Exception('Missing public key');
        }

        if ($sigConf['keyId'] !== $actor['publicKey']['id']) {
            throw new Exception('Signature keyId does not match actor public key id');
        }

        $actorPublicKey = openssl_get_publickey($actor['publicKey']['publicKeyPem']);
        if (!$actorPublicKey) {
            throw new Exception('Malformed public key');
        }

        // We cannot use getRequestUri() because it returns the real URI, eg. /?api/forms/2/actor
        $requestUri = $request->getScriptName();

        $sigParts = [];
        foreach (explode(' ', $sigConf['headers']) as $headerKey) {
            if ($headerKey === '(request-target)') {
                $sigParts[] = sprintf('%s: %s %s', $headerKey, strtolower($request->getMethod()), $requestUri);
            } else {
                if (!$request->headers->has($headerKey)) {
                    throw new Exception('Missing signature part: ' . $headerKey);
                }
                $sigParts[] = sprintf('%s: %s', $headerKey, $request->headers->get($headerKey));
            }
        }

        if (openssl_verify(join("\n", $sigParts), base64_decode($sigConf['signature']), $actorPublicKey, strtoupper($sigConf['algorithm'])) !== 1) {
            throw new Exception('Signature verification failed');
        }

        if ($request->headers->get('Digest') !== $this->getDigest($request->getContent())) {
            throw new Exception('Digest mismatch');
        }

        return $this->keyOwner($sigConf['keyId'], $actor);
    }

    /**
     * Who the key belongs to, taken from the document the keyId was fetched from.
     *
     * A document is free to claim any owner, so a key only ever speaks for an actor served by
     * the same host: without that, anyone could publish a key document naming someone else.
     *
     * @param array<string,mixed> $actor
     *
     * @throws Exception
     */
    protected function keyOwner(string $keyId, array $actor): string {
        $owner = $actor['publicKey']['owner'] ?? ($actor['id'] ?? null);
        if (empty($owner) || !is_string($owner)) {
            throw new Exception('The signing key names no owner');
        }
        if (isset($actor['id'], $actor['publicKey']['owner']) && $actor['id'] !== $actor['publicKey']['owner']) {
            throw new Exception('The actor and its key disagree on who owns the key');
        }
        if (!$this->sameHost($keyId, $owner)) {
            throw new Exception('The signing key and the actor it claims are not on the same host');
        }

        return $owner;
    }

    public function sameHost(string $first, string $second): bool {
        $firstParts = parse_url($first);
        $secondParts = parse_url($second);
        if (empty($firstParts['host']) || empty($secondParts['host'])) {
            return false;
        }

        return strtolower($firstParts['host']) === strtolower($secondParts['host'])
            && strtolower($firstParts['scheme'] ?? '') === strtolower($secondParts['scheme'] ?? '')
            && ($firstParts['port'] ?? null) === ($secondParts['port'] ?? null);
    }
}
