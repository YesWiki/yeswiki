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
        $newModeActivated = false;
        // TODO determine needRehash according to DB params
        if ($newModeActivated){
            $params = [
                'md5' => [
                    'class' => MD5PasswordHasher::class,
                    'arguments' => [true] 
                ],
                User::class => [
                    'algorithm' => 'auto',
                    'migrate_from' => [
                        'md5' // uses the "md5" hasher configured above
                    ]
                ]
            ];
        } else {
            $params = [
                User::class => [
                    'class' => MD5PasswordHasher::class,
                    'arguments' => [false]
                ]
            ];
        }
        parent::__construct($params);
    }
}