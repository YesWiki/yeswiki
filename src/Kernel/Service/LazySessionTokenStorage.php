<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;

/** CSRF tokens kept in `$_SESSION` like Symfony's native storage, without opening a session: HttpCacheHeaders persists them. */
class LazySessionTokenStorage implements ClearableTokenStorageInterface
{
    public const NAMESPACE = '_csrf';

    public function getToken(string $tokenId): string
    {
        if (!isset($_SESSION[self::NAMESPACE][$tokenId])) {
            throw new TokenNotFoundException('The CSRF token with ID ' . $tokenId . ' does not exist.');
        }

        return (string)$_SESSION[self::NAMESPACE][$tokenId];
    }

    public function setToken(string $tokenId, #[\SensitiveParameter] string $token): void
    {
        $_SESSION ??= [];
        $_SESSION[self::NAMESPACE][$tokenId] = $token;
    }

    public function hasToken(string $tokenId): bool
    {
        return isset($_SESSION[self::NAMESPACE][$tokenId]);
    }

    public function removeToken(string $tokenId): ?string
    {
        if (!isset($_SESSION[self::NAMESPACE][$tokenId])) {
            return null;
        }
        $token = (string)$_SESSION[self::NAMESPACE][$tokenId];
        unset($_SESSION[self::NAMESPACE][$tokenId]);
        if ($_SESSION[self::NAMESPACE] === []) {
            unset($_SESSION[self::NAMESPACE]);
        }

        return $token;
    }

    public function clear(): void
    {
        unset($_SESSION[self::NAMESPACE]);
    }
}
