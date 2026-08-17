<?php

namespace YesWiki\Identity\Security;

/** Recognises password hashes written by YesWikis that used md5(). */
final class LegacyPasswordHash
{
    /** Whether a stored hash is md5 output: 32 hexadecimal characters and nothing else. */
    public static function isMd5(?string $hashedPassword): bool
    {
        return is_string($hashedPassword) && preg_match('/^[0-9a-f]{32}$/i', $hashedPassword) === 1;
    }
}
