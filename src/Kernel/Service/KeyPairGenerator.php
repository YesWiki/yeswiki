<?php

namespace YesWiki\Kernel\Service;

/** An RSA keypair, in PEM. */
class KeyPairGenerator
{
    /** 2048 bits: what the ActivityPub implementations in the wild expect to verify against. */
    private const BITS = 2048;

    /**
     * @return array{0: string, 1: string} the private key and the public key, PEM-encoded
     *
     * @throws \RuntimeException if the platform's OpenSSL cannot produce one -- silently
     *                           returning an empty key would store a form that can sign
     *                           nothing and fail much later, at the first outbound request
     */
    public function generate(): array
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => self::BITS,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) {
            throw new \RuntimeException('could not generate an RSA keypair: ' . (string)openssl_error_string());
        }

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);
        if ($details === false || !isset($details['key'])) {
            throw new \RuntimeException('generated a private key with no public half');
        }

        return [(string)$privateKey, (string)$details['key']];
    }
}
