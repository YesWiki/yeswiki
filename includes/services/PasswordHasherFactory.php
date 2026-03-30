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
            $result = $this->dbService->query("SHOW COLUMNS FROM {$this->dbService->prefixTable('users')} LIKE 'password';");
            if (!$result) {
                return false;
            }
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                return false;
            }

            return !empty($row['Type']) && $row['Type'] == 'varchar(256)';
        } catch (Throwable $th) {
            return false;
        }
    }

    public function activateNewMode(): bool
    {
        return $this->dbService->query("ALTER TABLE {$this->dbService->prefixTable('users')} MODIFY COLUMN `password` varchar(256) NOT NULL;");
    }
}
