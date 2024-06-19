<?php

// inspiring from https://symfony.com/doc/5.4/security/passwords.html

namespace YesWiki\Core;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\PasswordHasher\Hasher\CheckPasswordLengthTrait;
use Symfony\Component\PasswordHasher\LegacyPasswordHasherInterface;

class MD5PasswordHasher implements LegacyPasswordHasherInterface
{
    use CheckPasswordLengthTrait;
    protected $needRehash;

    public function __construct(bool $needRehash)
    {
        $this->needRehash = $needRehash;
    }

    /**
     * Hashes a plain password.
     *
     * @throws InvalidPasswordException If the plain password is invalid, e.g. excessively long
     */
    public function hash(string $plainPassword, string $salt = null): string
    {
        if ($this->isPasswordTooLong($plainPassword)) {
            throw new InvalidPasswordException();
        }

        return md5($plainPassword);
    }

    /**
     * Checks that a plain password and a salt match a password hash.
     */
    public function verify(string $hashedPassword, string $plainPassword, string $salt = null): bool
    {
        return $hashedPassword === $this->hash($plainPassword);
    }

    /**
     * Checks if a password hash would benefit from rehashing.
     */
    public function needsRehash(string $hashedPassword): bool
    {
        return $this->needRehash;
    }
}
