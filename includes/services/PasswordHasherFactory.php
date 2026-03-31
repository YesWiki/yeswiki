<?php

namespace YesWiki\Core\Service;

require_once 'includes/objects/MD5PasswordHasher.php'; // TODO use autoload

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory as SymfonyPasswordHasherFactory;
use Throwable;
use YesWiki\Core\Entity\User;
use YesWiki\Core\MD5PasswordHasher;

class PasswordHasherFactory extends SymfonyPasswordHasherFactory
{
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
            $columnInfo = $this->dbService->getColumnInfo('users', 'password');
            if (empty($columnInfo)) {
                return false;
            }

            // Check if the column type is varchar(256) - normalize comparison
            $type = strtolower($columnInfo['type']);
            return $type === 'varchar(256)' || $type === 'character varying(256)';
        } catch (Throwable $th) {
            return false;
        }
    }

    public function activateNewMode(): bool
    {
        return $this->dbService->modifyColumn('users', 'password', 'varchar(256)', true);
    }
}
