<?php

namespace YesWiki\Identity\Security;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Kernel\Service\SignedTokens;

/**
 * CSRF tokens that need no session (replicas 13): a token is its expiry and the HMAC of its id, the visitor it was given to and that
 * expiry, recomputed to check it, so a form one replica showed posts to another. Nothing is stored, so removing or refreshing is
 * issuing again; RequestGuard's Origin check stays the second barrier.
 */
final class SignedCsrfTokenManager extends CsrfTokenManager
{
    public const LIFETIME = 24 * 3600;

    /**
     * @param \Closure(): string $visitor whom the tokens of this request are bound to
     * @param \Closure(): int    $now
     */
    public function __construct(
        private readonly SignedTokens $signed,
        private readonly \Closure $visitor,
        private readonly ?\Closure $now = null,
    ) {
        parent::__construct();
    }

    public function getToken(string $tokenId): CsrfToken
    {
        $expires = (intdiv($this->now(), 3600) + 25) * 3600;

        return new CsrfToken($tokenId, $expires . '.' . $this->signature($tokenId, $expires));
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return $this->getToken($tokenId);
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        $parts = explode('.', $token->getValue(), 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0])) {
            return false;
        }
        $expires = (int)$parts[0];
        $now = $this->now();
        if ($expires < $now || $expires > $now + self::LIFETIME + 3600) {
            return false;
        }

        return hash_equals($this->signature($token->getId(), $expires), $parts[1]);
    }

    private function signature(string $tokenId, int $expires): string
    {
        return $this->signed->sign('csrf|' . $tokenId . '|' . ($this->visitor)() . '|' . $expires);
    }

    private function now(): int
    {
        return $this->now === null ? time() : ($this->now)();
    }
}
