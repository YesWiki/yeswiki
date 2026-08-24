<?php

namespace YesWiki\Admin\Service;

/** Creates the database and the account a new wiki will own, using credentials it is handed once. */
class DatabaseProvisioner
{
    public const DRIVERS = ['mysql', 'pgsql'];

    /** @var list<string> */
    private array $done = [];

    /** @var array<string, mixed> */
    private array $config = [];

    public static function supports(string $driver): bool
    {
        return in_array($driver, self::DRIVERS, true);
    }

    public static function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    /**
     * What was created, in the order it was created, for an operator who has to find it later.
     *
     * @return list<string>
     */
    public function done(): array
    {
        return $this->done;
    }

    /**
     * @param array<string, mixed> $config the wiki's own configuration, naming what to create
     *
     * @throws \Exception when the administrative connection or any statement fails
     */
    public function provision(string $adminUser, string $adminPassword, array $config): void
    {
        $this->config = $config;
        $this->done = [];

        $driver = (string)($this->config['db_driver'] ?? '');
        if (!self::supports($driver)) {
            throw new \Exception('Provisioning is for ' . implode(' and ', self::DRIVERS) . ", not $driver.");
        }

        $database = $this->named('db_database');
        $user = $this->named('db_user');
        $password = (string)($this->config['db_password'] ?? '');

        if ($password === '') {
            throw new \Exception('The wiki needs a database password of its own before it can be granted anything.');
        }

        $link = $this->connectAsAdministrator($driver, $adminUser, $adminPassword);

        if ($driver === 'mysql') {
            $this->mysql($link, $database, $user, $password);
        } else {
            $this->postgres($link, $database, $user, $password);
        }
    }

    /**
     * Databases that are not a wiki's to drop, whatever a configuration file says.
     *
     * @var list<string>
     */
    public const NEVER_DROP = ['mysql', 'information_schema', 'performance_schema', 'sys', 'postgres', 'template0', 'template1'];

    /**
     * Drop the database and the account a wiki owned.
     *
     * @param array<string, mixed> $config the wiki's own configuration, naming what to drop
     *
     * @throws \Exception when the administrative connection or any statement fails
     */
    public function destroy(string $adminUser, string $adminPassword, array $config): void
    {
        $this->config = $config;
        $this->done = [];

        $driver = (string)($this->config['db_driver'] ?? '');
        if (!self::supports($driver)) {
            throw new \Exception('Dropping is for ' . implode(' and ', self::DRIVERS) . ", not $driver.");
        }

        $database = $this->named('db_database');
        $user = $this->named('db_user');

        if (in_array(strtolower($database), self::NEVER_DROP, true)) {
            throw new \Exception("$database is the server's own database, not this wiki's.");
        }
        if (strcasecmp($user, $adminUser) === 0) {
            throw new \Exception("$user is the account doing the dropping: a wiki that was installed with the administrator's own credentials has no account of its own to remove.");
        }

        $link = $this->connectAsAdministrator($driver, $adminUser, $adminPassword);

        if ($driver === 'mysql') {
            $link->exec("DROP DATABASE IF EXISTS `$database`");
            $this->done[] = "database $database";
            $statement = $link->prepare('DROP USER IF EXISTS ?@\'%\'');
            $statement->execute([$user]);
        } else {
            $link->exec("DROP DATABASE IF EXISTS \"$database\"");
            $this->done[] = "database $database";
            if ($this->postgresHas($link, 'pg_roles', 'rolname', $user)) {
                $link->exec("DROP ROLE \"$user\"");
            }
        }

        $this->done[] = "user $user";
    }

    private function named(string $key): string
    {
        $value = trim((string)($this->config[$key] ?? ''));

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,62}$/', $value)) {
            throw new \Exception("'$value' is not a name this can create: letters, digits and underscores, starting with a letter.");
        }

        return $value;
    }

    private function connectAsAdministrator(string $driver, string $adminUser, string $adminPassword): \PDO
    {
        $host = (string)($this->config['db_host'] ?? '127.0.0.1');
        $port = trim((string)($this->config['db_port'] ?? ''));

        $dsn = $driver === 'mysql'
            ? "mysql:host=$host" . ($port !== '' ? ";port=$port" : '')
            : "pgsql:host=$host" . ($port !== '' ? ";port=$port" : '') . ';dbname=postgres';

        return new \PDO($dsn, $adminUser, $adminPassword, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function mysql(\PDO $link, string $database, string $user, string $password): void
    {
        $link->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $this->done[] = "database $database";

        $statement = $link->prepare('CREATE USER IF NOT EXISTS ?@\'%\' IDENTIFIED BY ?');
        $statement->execute([$user, $password]);
        $this->done[] = "user $user";

        $link->exec("GRANT ALL PRIVILEGES ON `$database`.* TO '$user'@'%'");
        $link->exec('FLUSH PRIVILEGES');
        $this->done[] = "grant on $database to $user";
    }

    private function postgres(\PDO $link, string $database, string $user, string $password): void
    {
        if (!$this->postgresHas($link, 'pg_database', 'datname', $database)) {
            $link->exec("CREATE DATABASE \"$database\" ENCODING 'UTF8'");
        }
        $this->done[] = "database $database";

        if (!$this->postgresHas($link, 'pg_roles', 'rolname', $user)) {
            $link->exec("CREATE ROLE \"$user\" LOGIN PASSWORD " . $link->quote($password));
        }
        $this->done[] = "user $user";

        $link->exec("GRANT ALL PRIVILEGES ON DATABASE \"$database\" TO \"$user\"");
        $this->done[] = "grant on $database to $user";
    }

    private function postgresHas(\PDO $link, string $catalog, string $column, string $name): bool
    {
        $statement = $link->prepare("SELECT 1 FROM $catalog WHERE $column = ?");
        $statement->execute([$name]);

        return $statement->fetchColumn() !== false;
    }
}
