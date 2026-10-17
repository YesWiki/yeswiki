<?php

namespace YesWiki\Federation\Service;

use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\KeyPairGenerator;

/** Each form actor's RSA key, in a protected file named after the form id, out of the database and the API. */
class ActivityPubKeyStore
{
    public const DIRECTORY = 'private/keys/activitypub';

    /** @var array<string, string> */
    private array $privateKeys = [];

    public function __construct(private readonly Storage $storage, private readonly KeyPairGenerator $keyPairGenerator)
    {
    }

    /** The account's private key in PEM, generated on first use. */
    public function privateKey(string $account): string
    {
        if (isset($this->privateKeys[$account])) {
            return $this->privateKeys[$account];
        }
        $file = $this->file($account);
        if (!$this->storage->exists($file)) {
            [$privateKey] = $this->keyPairGenerator->generate();
            $this->storage->write($file, $privateKey);
        }
        $pem = $this->storage->read($file);
        if (openssl_pkey_get_private($pem) === false) {
            throw new \Exception("Unreadable ActivityPub key for {$account}");
        }

        return $this->privateKeys[$account] = $pem;
    }

    public function publicKey(string $account): string
    {
        $key = openssl_pkey_get_private($this->privateKey($account));
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($details === false || !is_string($details['key'] ?? null)) {
            throw new \Exception("Unreadable ActivityPub key for {$account}");
        }

        return $details['key'];
    }

    /** Keeps a key an older version stored elsewhere, unless the account already has one. */
    public function import(string $account, string $privateKey): void
    {
        $file = $this->file($account);
        if (!$this->storage->exists($file) && openssl_pkey_get_private($privateKey) !== false) {
            $this->storage->write($file, $privateKey);
        }
    }

    private function file(string $account): string
    {
        if (!preg_match('/^[\w\-]+$/', $account)) {
            throw new \Exception('Invalid ActivityPub account');
        }

        return self::DIRECTORY . '/' . $account . '.pem';
    }
}
