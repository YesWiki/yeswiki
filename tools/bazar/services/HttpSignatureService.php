<?php

namespace YesWiki\Bazar\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;

class HttpSignatureService
{
    public const MAX_CLOCK_SKEW = 3600;
    public const CONTENT_TYPE = 'application/activity+json';
    public const ACCEPT = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';
    public const TIMEOUT = 5;
    public const MAX_DURATION = 15;

    protected $httpClient;
    protected $ssrfUrlValidator;
    protected $seenSignaturesDir;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator, string $seenSignaturesDir = 'cache/activitypub-signatures')
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
        $this->seenSignaturesDir = $seenSignaturesDir;
    }

    public function generateKeyPair()
    {
        $result = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($result, $privKey);

        $pubKey = openssl_pkey_get_details($result);
        $pubKey = $pubKey['key'];

        return [$privKey, $pubKey];
    }

    public function getDigest($message)
    {
        return 'SHA-256=' . base64_encode(hash('sha256', $message, true));
    }

    /**
     * Headers signing a request with an actor's key, covering the body when there is one.
     *
     * @param array{keyId:string,privateKey:string} $signer
     *
     * @return array<string,string>
     */
    public function signHeaders(string $method, string $url, array $signer, ?string $body = null): array
    {
        $urlParts = parse_url($url);
        $target = ($urlParts['path'] ?? '/') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        $date = gmdate('D, d M Y H:i:s \G\M\T');

        $headers = ['Date' => $date];
        $sigParts = [
            '(request-target)' => strtolower($method) . ' ' . $target,
            'host' => $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : ''),
            'date' => $date,
        ];
        if ($body !== null) {
            $headers['Digest'] = $sigParts['digest'] = $this->getDigest($body);
            $headers['Content-Type'] = $sigParts['content-type'] = self::CONTENT_TYPE;
        }

        $sigSrc = join("\n", array_map(
            fn ($k, $v) => "{$k}: {$v}",
            array_keys($sigParts),
            array_values($sigParts)
        ));

        if (!openssl_sign($sigSrc, $signature, $signer['privateKey'], OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Cannot sign the request');
        }

        $headers['Signature'] = sprintf(
            'keyId="%s",algorithm="rsa-sha256",headers="%s",signature="%s"',
            $signer['keyId'],
            join(' ', array_keys($sigParts)),
            base64_encode($signature)
        );

        return $headers;
    }

    /**
     * Fetches an ActivityPub document, signing the request when a signer is given.
     *
     * @param array{keyId:string,privateKey:string}|null $signer
     */
    public function fetch(string $url, ?array $signer = null): array
    {
        $headers = ['Accept' => self::ACCEPT];
        if ($signer !== null) {
            $headers += $this->signHeaders('GET', $url, $signer);
        }

        $response = $this->httpClient->request('GET', $url, [
            'headers' => $headers,
            'max_redirects' => 0,
            'timeout' => self::TIMEOUT,
            'max_duration' => self::MAX_DURATION,
            'resolve' => $this->ssrfUrlValidator->resolveSafe($url),
        ]);

        $document = json_decode($response->getContent(), true);
        if (!is_array($document)) {
            throw new \Exception("{$url} did not return an ActivityPub document");
        }

        return $document;
    }

    /**
     * Check the request really was signed by the key it names, and say whose key it was.
     *
     * A valid signature only answers "was this signed"; the caller still has to ask "by whom",
     * so the owner of the key is returned for it to bind the activity to.
     *
     * @return string the actor the verified key belongs to
     *
     * @throws \Exception when anything about the signature does not hold
     */
    public function verifySignature(Request $request, ?array $signer = null, ?string $target = null): string
    {
        if (!$request->headers->has('Signature')) {
            throw new \Exception('No signature');
        }

        if ($request->headers->has('Signature-Input')) {
            throw new \Exception('RFC 9421 signatures are not supported');
        }

        $sigConf = $this->parseSignatureHeader((string)$request->headers->get('Signature'));
        $sigConf['algorithm'] ??= 'hs2019';

        if (!isset($sigConf['keyId'],$sigConf['headers'],$sigConf['signature'])) {
            throw new \Exception('Malformed signature');
        }

        $this->assertFresh($request, explode(' ', strtolower($sigConf['headers'])));

        $algorithm = $this->opensslAlgorithm($sigConf['algorithm']);
        $actor = $this->fetch($sigConf['keyId'], $signer);

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

        $requestUri = $target ?? $request->getScriptName();

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

        if (openssl_verify(join("\n", $sigParts), base64_decode($sigConf['signature']), $actorPublicKey, $algorithm) !== 1) {
            throw new \Exception('Signature verification failed');
        }

        if ($request->headers->get('Digest') !== $this->getDigest($request->getContent())) {
            throw new \Exception('Digest mismatch');
        }

        $owner = $this->keyOwner($sigConf['keyId'], $actor);
        $this->assertNotSeen($sigConf['signature']);

        return $owner;
    }

    /**
     * The parameters of a draft-cavage Signature header, or an empty array when it is not one.
     *
     * @return array<string,string>
     */
    protected function parseSignatureHeader(string $header): array
    {
        $parameters = [];
        foreach (preg_split('/,(?=\s*[A-Za-z]+\s*=\s*")/', trim($header)) as $parameter) {
            if (!preg_match('/^\s*([A-Za-z]+)\s*=\s*"([^"]*)"\s*$/', $parameter, $matches)) {
                return [];
            }
            $parameters[$matches[1]] = $matches[2];
        }

        return $parameters;
    }

    /**
     * The OpenSSL digest for a signature algorithm, hs2019 being RSA with SHA-256 for every server seen so far.
     */
    protected function opensslAlgorithm(string $algorithm): int
    {
        return match (strtolower($algorithm)) {
            'rsa-sha256', 'hs2019' => OPENSSL_ALGO_SHA256,
            'rsa-sha512' => OPENSSL_ALGO_SHA512,
            default => throw new \Exception('Unsupported signature algorithm'),
        };
    }

    /**
     * Refuse a request whose date and body are not signed, or whose date is too far from now.
     *
     * @param string[] $signedHeaders
     *
     * @throws \Exception
     */
    protected function assertFresh(Request $request, array $signedHeaders): void
    {
        foreach (['date', 'digest'] as $required) {
            if (!in_array($required, $signedHeaders, true)) {
                throw new \Exception("The signature does not cover the {$required} header");
            }
        }
        $date = strtotime((string)$request->headers->get('Date'));
        if ($date === false || abs($this->now() - $date) > self::MAX_CLOCK_SKEW) {
            throw new \Exception('The request is dated too far from now');
        }
    }

    /**
     * Refuse a signature already accepted, remembering each one for as long as its date stays acceptable.
     *
     * @throws \Exception
     */
    protected function assertNotSeen(string $signature): void
    {
        if (!is_dir($this->seenSignaturesDir) && !mkdir($this->seenSignaturesDir, 0700, true) && !is_dir($this->seenSignaturesDir)) {
            throw new \Exception('Cannot record the signature');
        }
        foreach (glob($this->seenSignaturesDir . '/*') ?: [] as $file) {
            if (filemtime($file) < $this->now() - 2 * self::MAX_CLOCK_SKEW) {
                @unlink($file);
            }
        }
        $file = $this->seenSignaturesDir . '/' . hash('sha256', $signature);
        $handle = @fopen($file, 'x');
        if ($handle === false) {
            throw new \Exception('This request has already been received');
        }
        fclose($handle);
        touch($file, $this->now());
    }

    protected function now(): int
    {
        return time();
    }

    /**
     * Who the key belongs to, taken from the document the keyId was fetched from.
     *
     * A document is free to claim any owner, so a key only ever speaks for an actor served by
     * the same host: without that, anyone could publish a key document naming someone else.
     *
     * @param array<string,mixed> $actor
     *
     * @throws \Exception
     */
    protected function keyOwner(string $keyId, array $actor): string
    {
        $owner = $actor['publicKey']['owner'] ?? ($actor['id'] ?? null);
        if (empty($owner) || !is_string($owner)) {
            throw new \Exception('The signing key names no owner');
        }
        if (isset($actor['id'], $actor['publicKey']['owner']) && $actor['id'] !== $actor['publicKey']['owner']) {
            throw new \Exception('The actor and its key disagree on who owns the key');
        }
        if (!$this->sameHost($keyId, $owner)) {
            throw new \Exception('The signing key and the actor it claims are not on the same host');
        }

        return $owner;
    }

    public function sameHost(string $first, string $second): bool
    {
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
