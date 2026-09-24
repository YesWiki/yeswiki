<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\Storage;

/** Tokens that are the same for every visitor, signed with the wiki's own key, for pages a shared cache may keep. */
class SignedTokens
{
    public const KEY_FILE = 'private/keys/signing.key';

    private ?string $key = null;

    public function __construct(private readonly Storage $storage)
    {
    }

    /** The token that proves this wiki rendered a call to `$id`. */
    public function sign(string $id): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $id, $this->key(), true)), '+/', '-_'), '=');
    }

    /** Whether `$token` is this wiki's signature of `$id`. */
    public function verify(string $id, string $token): bool
    {
        return $token !== '' && hash_equals($this->sign($id), $token);
    }

    /** The signing key, created on first use and shared by every node that shares the wiki's protected storage. */
    private function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }
        if (!$this->storage->exists(self::KEY_FILE)) {
            $this->storage->write(self::KEY_FILE, bin2hex(random_bytes(32)));
        }

        return $this->key = trim($this->storage->read(self::KEY_FILE));
    }
}
