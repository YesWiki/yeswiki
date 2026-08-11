<?php

namespace YesWiki\Identity\Service;

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory as SymfonyPasswordHasherFactory;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Security\MD5PasswordHasher;
use YesWiki\Kernel\Service\DbService;

class PasswordHasherFactory extends SymfonyPasswordHasherFactory
{
    /** Hasher name for form password fields, as opposed to user-account passwords. */
    public const BAZAR_FIELD = 'bazar_field';

    protected $dbService;

    public function __construct(DbService $dbService)
    {
        $this->dbService = $dbService;
        $params = [
            'md5' => [
                'class' => MD5PasswordHasher::class,
                'arguments' => [true],
            ],
            User::class => [
                'algorithm' => 'auto',
                'migrate_from' => [
                    'md5', // uses the "md5" hasher configured above
                ],
            ],
            // Password fields inside bazar forms (PasswordField / `mot_de_passe`).
            // Same deal as user accounts: PHP's current best algorithm for new values,
            // and the md5 hasher above kept only to verify what older YesWikis stored.
            self::BAZAR_FIELD => [
                'algorithm' => 'auto',
                'migrate_from' => [
                    'md5',
                ],
            ],
            'cookie' => [
                'algorithm' => 'bcrypt',
                'cost' => 9, // default 13, 9 less difficult to be faster
            ],
        ];
        parent::__construct($params);
    }

    public function newModeIsActivated(): bool
    {
        try {
            $columnInfo = $this->dbService->schema()->getColumnInfo('users', 'password');
            if (empty($columnInfo)) {
                return false;
            }

            // Check if the column type is varchar(256) - normalize comparison
            $type = strtolower($columnInfo['type']);

            return $type === 'varchar(256)' || $type === 'character varying(256)';
        } catch (\Throwable $th) {
            return false;
        }
    }

    public function activateNewMode(): bool
    {
        return $this->dbService->schema()->modifyColumn('users', 'password', 'varchar(256)', true);
    }
}
