<?php

namespace YesWiki\Identity\Service;

use YesWiki\Kernel\Service\SignedTokens;

/**
 * The `yw-auth` cookie: who is signed in, since when, until when, and under which generation of their account, signed with the wiki's
 * key, so any replica checks it with an HMAC rather than a session or a bcrypt (replicas 12). It says who the user is, never what they may do.
 */
class LoginCookie
{
    public const NAME = 'yw-auth';

    public const SHORT_LIFETIME = 3600;

    public const REMEMBERED_LIFETIME = 90 * 24 * 3600;

    public function __construct(private readonly SignedTokens $tokens)
    {
    }

    /** The cookie's value for that sign-in. */
    public function issue(string $name, bool $remember, int $generation, int $now): string
    {
        $payload = self::encode((string)json_encode([
            'n' => $name,
            'i' => $now,
            'e' => $now + ($remember ? self::REMEMBERED_LIFETIME : self::SHORT_LIFETIME),
            'r' => $remember ? 1 : 0,
            'g' => $generation,
        ]));

        return $payload . '.' . $this->tokens->sign(self::NAME . ':' . $payload);
    }

    /**
     * What a cookie says, when this wiki signed it and it has not expired; null otherwise.
     *
     * @return array{name: string, issued: int, expires: int, remember: bool, generation: int}|null
     */
    public function read(string $cookie, int $now): ?array
    {
        $parts = explode('.', $cookie);
        if (count($parts) !== 2 || !$this->tokens->verify(self::NAME . ':' . $parts[0], $parts[1])) {
            return null;
        }
        $data = json_decode((string)base64_decode(strtr($parts[0], '-_', '+/'), true), true);
        if (!is_array($data) || !is_string($data['n'] ?? null) || $data['n'] === '' || !is_int($data['i'] ?? null) || !is_int($data['e'] ?? null) || !is_int($data['g'] ?? null)) {
            return null;
        }
        if ($data['e'] < $now) {
            return null;
        }

        return ['name' => $data['n'], 'issued' => $data['i'], 'expires' => $data['e'], 'remember' => !empty($data['r']), 'generation' => $data['g']];
    }

    /**
     * Whether a cookie is more than half-way to its expiry, so that an active user gets a fresh one and is not signed out after an hour.
     *
     * @param array{issued: int, expires: int} $read
     */
    public static function isDueForRenewal(array $read, int $now): bool
    {
        return $now > $read['issued'] + intdiv($read['expires'] - $read['issued'], 2);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
