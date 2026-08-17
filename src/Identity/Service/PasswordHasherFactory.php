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
        $params = [
            User::class => [
                'algorithm' => 'auto',
            ],

            self::BAZAR_FIELD => [
                'algorithm' => 'auto',
            ],
            'cookie' => [
                'algorithm' => 'bcrypt',
                'cost' => 9,
            ],
        ];
        parent::__construct($params);
    }
}
