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

        $date = date("D, d M Y H:i:s \G\M\T");

        $urlParts = parse_url($url);

        $sigParts = [
            '(request-target)' => 'post ' . $urlParts['path'],
            'host' => $urlParts['host'],
            'date' => $date,
            'digest' => $digest,
            'content-type' => 'application/activity+json',
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
            'Digest' => $digest,
            'Signature' => join(',', array_map(
                    fn($k, $v) => "{$k}=\"{$v}\"",
                    array_keys($sigHeaderParts),
                    array_values($sigHeaderParts)
                )),
        ];
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

        $sigParts = [];
        foreach (explode(' ', $sigConf['headers']) as $headerKey) {
            if ($headerKey === '(request-target)') {
                $sigParts[] = sprintf('%s: %s %s', $headerKey, strtolower($request->getMethod()), $request->getRequestUri());
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
            var_dump('DIGEST1', $request->headers->get($headerKey));
            var_dump('DIGEST2', $this->getDigest($request->getContent()));
            throw new Exception('Digest mismatch');
        }
    }
}
