<?php

namespace YesWiki\Bazar\Service;

/**
 * Keeps each ActivityPub actor's RSA key pair in a private file named after its form id, out of the database and the API.
 */
class ActivityPubKeyStore
{
    protected $httpSignatureService;
    protected $directory;

    public function __construct(HttpSignatureService $httpSignatureService, string $directory = 'private/activitypub')
    {
        $this->httpSignatureService = $httpSignatureService;
        $this->directory = $directory;
    }

    /**
     * The account's private key in PEM, generated on first use.
     */
    public function privateKey(string $account): string
    {
        $file = $this->file($account);
        if (!is_file($file)) {
            $this->generate($file);
        }
        $pem = file_get_contents($file);
        if ($pem === false || openssl_pkey_get_private($pem) === false) {
            throw new \Exception("Unreadable ActivityPub key for {$account}");
        }

        return $pem;
    }

    public function publicKey(string $account): string
    {
        return openssl_pkey_get_details(openssl_pkey_get_private($this->privateKey($account)))['key'];
    }

    protected function file(string $account): string
    {
        if (!preg_match('/^[\w\-]+$/', $account)) {
            throw new \Exception('Invalid ActivityPub account');
        }

        return $this->directory . '/' . $account . '.pem';
    }

    protected function generate(string $file): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \Exception('Cannot create ' . $this->directory);
        }
        [$privateKey] = $this->httpSignatureService->generateKeyPair();
        $temporary = tempnam($this->directory, 'key');
        if ($temporary === false || file_put_contents($temporary, $privateKey) === false) {
            throw new \Exception('Cannot write the ActivityPub key');
        }
        chmod($temporary, 0600);
        @link($temporary, $file);
        unlink($temporary);
    }
}
