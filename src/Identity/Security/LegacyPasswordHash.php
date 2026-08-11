<?php

namespace YesWiki\Identity\Security;

/**
 * Recognises password hashes written by YesWikis that used md5().
 *
 * md5 is out: nothing in this codebase hashes with it any more, and a stored md5 is no
 * longer accepted as a credential -- unsalted and fast enough that a commodity GPU walks
 * the whole plausible keyspace, so verifying one is the same as trusting a plaintext
 * table that happens to be encoded.
 *
 * The stored hash is nonetheless left in place rather than blanked. That is what keeps a
 * legacy account recoverable: the row still exists, the lost-password flow can still find
 * it by name or email, and the hash is the marker that says "this account must reset"
 * (see AuthenticationService::requiresPasswordReset()). Blanking it would throw away the
 * only signal distinguishing an account that predates the change from one that never had
 * a password, and would let an empty stored value stand in for a real one.
 *
 * This class deliberately cannot hash. There is no md5 hasher left to reach for.
 */
final class LegacyPasswordHash
{
    /**
     * Whether a stored hash is md5 output: 32 hexadecimal characters and nothing else.
     *
     * Unambiguous here, because every hash this codebase writes comes from PHP's
     * password_hash() and therefore carries an algorithm prefix (`$2y$`, `$argon2id$`),
     * which no 32-character hex string can.
     */
    public static function isMd5(?string $hashedPassword): bool
    {
        return is_string($hashedPassword) && preg_match('/^[0-9a-f]{32}$/i', $hashedPassword) === 1;
    }
}
