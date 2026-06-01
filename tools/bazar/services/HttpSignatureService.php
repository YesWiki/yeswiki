<?php

namespace YesWiki\Bazar\Service;

use Exception;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;

class HttpSignatureService
{
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = HttpClient::create();
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
     * Validate that a keyId URL is safe to fetch: must be HTTPS and must not
     * resolve to a private, loopback, reserved, or link-local address (SSRF guard).
     */
    private function validateKeyIdUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new Exception('Invalid keyId URL');
        }
        if ($parts['scheme'] !== 'https') {
            throw new Exception('keyId URL must use HTTPS');
        }

        // Strip IPv6 brackets (e.g. [::1] → ::1)
        $host = trim($parts['host'], '[]');

        // Collect all IPs the host resolves to so every address family is checked.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            $ips = [];
            $ipv4 = gethostbyname($host);
            if ($ipv4 !== $host) {
                $ips[] = $ipv4;
            }
            foreach (dns_get_record($host, DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if (empty($ips)) {
                throw new Exception('Cannot resolve keyId hostname');
            }
        }

        foreach ($ips as $ip) {
            // Rejects 127.x, 10.x, 172.16–31.x, 192.168.x, 169.254.x, 0.x, 240.x, ::1, fc00::/7
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception('keyId URL resolves to a private or reserved address');
            }
            // fe80::/10 (IPv6 link-local) is not blocked by FILTER_FLAG_NO_RES_RANGE in all PHP versions
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && stripos($ip, 'fe80') === 0) {
                throw new Exception('keyId URL resolves to a private or reserved address');
            }
        }
    }

    public function verifySignature(Request $request) {
        if (!$request->headers->has('Signature')) {
            throw new Exception('No signature');
        }

        $sigConf = parse_ini_string(
            strtr($request->headers->get('Signature'), ["," => "\n"])
        );

        if (!isset($sigConf['keyId'],$sigConf['algorithm'],$sigConf['headers'],$sigConf['signature'])) {
            throw new Exception('Malformed signature');
        }

        $this->validateKeyIdUrl($sigConf['keyId']);

        $response = $this->httpClient->request('GET', $sigConf['keyId'], [
            'headers' => [ 'Accept' => 'application/ld+json']
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

        if (!openssl_verify(join("\n", $sigParts), base64_decode($sigConf['signature']), $actorPublicKey, strtoupper($sigConf['algorithm']))) {
            throw new Exception('Signature verification failed');
        }

        if ($request->headers->get('Digest') !== $this->getDigest($request->getContent())) {
            throw new Exception('Digest mismatch');
        }
    }
}
