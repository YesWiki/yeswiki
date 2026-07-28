<?php

namespace YesWiki\Kernel\Database;

class SqlDialectFactory
{
    /**
     * Unknown drivers get MySQL, preserving the `default:` branch of the switch statements
     * this replaced.
     */
    public static function forDriver(string $driver): SqlDialect
    {
        switch ($driver) {
            case 'sqlite':
                return new SqliteDialect();
            case 'pgsql':
                return new PostgreSqlDialect();
            case 'mysql':
            default:
                return new MySqlDialect();
        }
    }
}
