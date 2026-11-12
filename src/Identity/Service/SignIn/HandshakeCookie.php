<?php

namespace YesWiki\Identity\Service\SignIn;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\SignIn\Contract\Handshake;
use YesWiki\Kernel\Service\SignedTokens;
use YesWiki\Kernel\Service\WikiCookies;

/**
 * What a redirect sign-in remembers while the person is away, signed and kept in their own browser: no session, and any replica can read it.
 * Like `yw-auth`, the cookie is mirrored in `$_COOKIE` for what follows in the same request; a worker starts each request with a fresh copy.
 */
class HandshakeCookie
{
    public const NAME = 'yw-sign-in';

    public const LIFETIME = 600;

    public function __construct(
        private readonly SignedTokens $tokens,
        private readonly WikiCookies $cookies,
    ) {
    }

    public function encode(Handshake $h): string
    {
        $payload = rtrim(strtr(base64_encode((string)json_encode($h->toPayload())), '+/', '-_'), '=');

        return $payload . '.' . $this->tokens->sign(self::NAME . ':' . $payload);
    }

    public function issue(Handshake $h): void
    {
        $value = $this->encode($h);
        $this->send($value, $h->expiresAt);
        $_COOKIE[self::NAME] = $value;
    }

    /** The handshake a request brought back, when this wiki signed it and it has not expired; null otherwise. */
    public function read(Request $r, int $now): ?Handshake
    {
        $parts = explode('.', (string)$r->cookies->get(self::NAME, ''));
        if (count($parts) !== 2 || !$this->tokens->verify(self::NAME . ':' . $parts[0], $parts[1])) {
            return null;
        }
        $payload = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        $handshake = is_array($payload) ? Handshake::fromPayload($payload) : null;

        return $handshake !== null && $now < $handshake->expiresAt ? $handshake : null;
    }

    public function clear(): void
    {
        $this->send('', time() - 3600);
        unset($_COOKIE[self::NAME]);
    }

    /**
     * The wiki's options, except SameSite: SAML comes back with a cross-site POST, and a `Strict` wiki would lose the handshake on every provider.
     *
     * @return array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Lax'|'None'|'Strict'}
     */
    public function options(int $expires): array
    {
        $options = $this->cookies->options($expires);
        $options['samesite'] = $options['secure'] ? 'None' : 'Lax';

        return $options;
    }

    private function send(string $value, int $expires): void
    {
        if (!headers_sent()) {
            setcookie(self::NAME, $value, $this->options($expires));
        }
    }
}
