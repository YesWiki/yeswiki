<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\Journal;

/** Creates a configured wiki's schema the first time its database answers without one, and answers 503 while it does not answer at all (replicas 20). */
#[Exclude]
final class SchemaBootstrap
{
    /** @param array<string, mixed> $config makes sure the database this configuration names holds the wiki's schema */
    public static function ensure(array $config): void
    {
        $marker = self::marker($config);
        $files = new LocalFiles();
        if ($files->isFile($marker)) {
            return;
        }

        try {
            $service = new InstallationService($config, '');
            $admin = self::adminFromEnvironment();
            if ($admin !== null) {
                $service->withAdminAccount($admin['name'], $admin['email'], $admin['password']);
            }
            if ($service->createIfMissing()) {
                $created = $service->config();
                Journal::toStderr((string)($config['base_url'] ?? ''), [
                    'channel' => 'diagnostic',
                    'level' => 'warning',
                    'action' => 'schema.created',
                    'message' => 'schema created in ' . ($created['db_database'] ?? '') . ' with prefix ' . ($created['table_prefix'] ?? '')
                        . ($admin === null ? '; no admin account: add one with `yeswiki user:create <name> <email> --group admins`' : '; admin account ' . $admin['name']),
                ]);
            }
        } catch (\Throwable $failed) {
            $message = strip_tags(str_replace('<br />', ' ', $failed->getMessage()));
            if (PHP_SAPI === 'cli') {
                throw new \RuntimeException('The database does not answer: ' . $message, 0, $failed);
            }
            http_response_code(503);
            header('Retry-After: 10');
            header('Content-Type: text/plain; charset=UTF-8');
            echo _t('DB_CONNECT_FAIL') . "\n";
            exit;
        }

        if ($files->makeDirectory(dirname($marker))) {
            $files->touch($marker);
        }
    }

    /** @param array<string, mixed> $config the file whose presence says this database already holds the schema */
    private static function marker(array $config): string
    {
        $names = [];
        foreach (['db_driver', 'db_host', 'db_port', 'db_database', 'table_prefix'] as $key) {
            $value = $config[$key] ?? '';
            $names[] = is_scalar($value) ? (string)$value : '';
        }

        return YESWIKI_INSTANCE_DIR . '/cache/schema/' . sha1(implode("\0", $names));
    }

    /** @return array{name: string, email: string, password: string}|null the account ADMIN_* describe, null unless a name and a password are given */
    private static function adminFromEnvironment(): ?array
    {
        $name = trim((string)getenv('ADMIN_NAME'));
        $password = (string)getenv('ADMIN_PASSWORD');
        if ($name === '' || $password === '') {
            return null;
        }

        return ['name' => $name, 'email' => trim((string)getenv('ADMIN_EMAIL')), 'password' => $password];
    }
}
