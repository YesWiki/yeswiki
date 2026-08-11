<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory as SymfonyPasswordHasherFactory;
use YesWiki\Identity\Entity\User;

class PasswordHasherFactory extends SymfonyPasswordHasherFactory
{
    /** Hasher name for form password fields, as opposed to user-account passwords. */
    public const BAZAR_FIELD = 'bazar_field';

    public function __construct()
    {
        // No `migrate_from` anywhere: md5 is out. It used to be listed here so a stored md5
        // still logged in once and was rehashed on the way through, which meant the whole
        // installed base of md5 hashes stayed live credentials indefinitely -- one account
        // that never signed in again kept its md5 forever. `auto` alone refuses them, and
        // LegacyPasswordHash::isMd5() is what turns that refusal into an actionable
        // "reset your password" rather than a bare "wrong password".
        //
        // Symfony still wraps `auto` in a MigratingPasswordHasher with an empty chain, so
        // needsRehash() keeps reporting true for an md5 -- a stored one is replaced the
        // first time a plain password legitimately passes through (the reset flow).
        $params = [
            User::class => [
                'algorithm' => 'auto',
            ],
            // Password fields inside bazar forms (PasswordField / `mot_de_passe`).
            // Same deal as user accounts: PHP's current best algorithm, md5 refused.
            self::BAZAR_FIELD => [
                'algorithm' => 'auto',
            ],
            'cookie' => [
                'algorithm' => 'bcrypt',
                'cost' => 9, // default 13, 9 less difficult to be faster
            ],
        ];
        parent::__construct($params);
    }
}
