<?php

namespace YesWiki\Core\Service;

require_once 'includes/objects/MD5PasswordHasher.php'; // TODO use autoload

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory as SymfonyPasswordHasherFactory;
use YesWiki\Core\Entity\User;
use YesWiki\Core\MD5PasswordHasher;

class PasswordHasherFactory extends SymfonyPasswordHasherFactory
{
    public function __construct()
    {
        parent::__construct([
            'md5' => [
                'class' => MD5PasswordHasher::class,
                'arguments' => [false] // needRehash
                // TODO determine needRehash according to DB params
            ],
            User::class => [
                'algorithm' => 'md5', // TO choose 'auto' if DB could manage
                // 'migrate_from' => [
                //     'md5' // uses the "legacy" hasher configured above
                // ]
            ]
        ]);
    }
}