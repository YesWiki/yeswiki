<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory as SymfonyPasswordHasherFactory;
use YesWiki\Identity\Entity\User;

class PasswordHasherFactory extends SymfonyPasswordHasherFactory
{
    /** Hasher name for form password fields, as opposed to user-account passwords. */
    public const BAZAR_FIELD = 'bazar_field';

    /** The lowest bcrypt cost, used under the test suite where hashing time is pure overhead. */
    private const TEST_COST = 4;

    public function __construct(#[Autowire('%kernel.environment%')] string $environment = 'prod')
    {
        $cost = $environment === 'test' ? ['cost' => self::TEST_COST] : [];
        $params = [
            User::class => ['algorithm' => 'auto'] + $cost,
            self::BAZAR_FIELD => ['algorithm' => 'auto'] + $cost,
            'cookie' => ['algorithm' => 'bcrypt'] + $cost + ['cost' => 9],
        ];
        parent::__construct($params);
    }
}
