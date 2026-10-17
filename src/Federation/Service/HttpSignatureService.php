<?php

namespace YesWiki\Federation\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use YesWiki\Kernel\Service\SsrfUrlValidator;

class HttpSignatureService
{
    public const MAX_CLOCK_SKEW = 3600;
    public const CONTENT_TYPE = 'application/activity+json';
    public const ACCEPT = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';

    protected HttpClientInterface $httpClient;
    protected SsrfUrlValidator $ssrfUrlValidator;
    protected ?SeenSignatures $seenSignatures;

    public function __construct(SsrfUrlValidator $ssrfUrlValidator, ?SeenSignatures $seenSignatures = null)
    {
        $this->httpClient = HttpClient::create();
        $this->ssrfUrlValidator = $ssrfUrlValidator;
        $this->seenSignatures = $seenSignatures;
    }

    public function getDigest(string $message): string
    {
        return 'SHA-256=' . base64_encode(hash('sha256', $message, true));
    }

    /**
     * Headers signing a request with an actor's key, covering the body when there is one.
     *
     * @param array{keyId: string, privateKey: string} $signer
     *
     * @return array<string, string>
     */
    public function signHeaders(string $method, string $url, array $signer, ?string $body = null): array
    {
        $urlParts = parse_url($url);
        if (!is_array($urlParts) || !isset($urlParts['host'])) {
            throw new \Exception("cannot sign a request for '$url': it has no host");
        }
        $target = ($urlParts['path'] ?? '/') . (isset($urlParts['query']) ? '?' . $urlParts['query'] : '');
        $date = gmdate('D, d M Y H:i:s \\G\\M\\T');

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
     * @param array{keyId: string, privateKey: string}|null $signer
     *
     * @return array<string, mixed>
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
     * A valid signature only answers "was this signed"; the caller still has to ask "by whom", so the owner of the key is returned for it to bind the activity to.
     *
     * @param array{keyId: string, privateKey: string}|null $signer signs the fetch of the key, as GoToSocial requires
     * @param string|null                                   $target the path the sender signed, when the server rewrote the one PHP sees
     *
     * @return string the actor the verified key belongs to
     */
    public function verifySignature(Request $request, ?array $signer = null, ?string $target = null): string
    {
        $signatureHeader = $request->headers->get('Signature');
        if ($signatureHeader === null || $signatureHeader === '') {
            throw new \Exception('No signature');
        }

        $sigConf = parse_ini_string(
            strtr($signatureHeader, [',' => "\n"])
        );

        if (!isset($sigConf['keyId'],$sigConf['algorithm'],$sigConf['headers'],$sigConf['signature'])) {
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

    /** The OpenSSL digest for a signature algorithm, hs2019 being RSA with SHA-256 for every server seen so far. */
    protected function opensslAlgorithm(string $algorithm): int
    {
        return match (strtolower($algorithm)) {
            'rsa-sha256', 'hs2019' => OPENSSL_ALGO_SHA256,
            'rsa-sha512' => OPENSSL_ALGO_SHA512,
            default => throw new \Exception('Unsupported signature algorithm'),
        };
    }

    /**
     * Refuses a request whose date and body are not signed, or whose date is too far from now.
     *
     * @param string[] $signedHeaders
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

    /** Refuses a signature already accepted, remembering each one for as long as its date stays acceptable. */
    protected function assertNotSeen(string $signature): void
    {
        if ($this->seenSignatures === null) {
            throw new \LogicException('No store of seen signatures to check this request against');
        }
        if (!$this->seenSignatures->remember($signature, $this->now(), $this->now() - 2 * self::MAX_CLOCK_SKEW)) {
            throw new \Exception('This request has already been received');
        }
    }

    protected function now(): int
    {
        return time();
    }

    /**
     * Who the key belongs to, taken from the document the keyId was fetched from.
     *
     * A document is free to claim any owner, so a key only ever speaks for an actor served by the same host: without that, anyone could publish a key document naming someone else.
     *
     * @param array<string,mixed> $actor
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
