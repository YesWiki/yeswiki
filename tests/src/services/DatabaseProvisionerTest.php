<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\DatabaseProvisioner;

#[CoversMethod(DatabaseProvisioner::class, 'provision')]
class DatabaseProvisionerTest extends TestCase
{
    /**
     * A closed local port, so the tests that get as far as connecting are refused at once rather than waiting out a timeout: what they are about is what happens before the connection.
     *
     * @param array<string, mixed> $overrides
     */
    private function provisionFor(array $overrides = []): void
    {
        (new DatabaseProvisioner())->provision('root', 'secret', array_merge([
            'db_driver' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_port' => '1',
            'db_database' => 'wiki_one',
            'db_user' => 'wiki_one',
            'db_password' => 'generated',
        ], $overrides));
    }

    public function testOnlyServerDatabasesHaveAnythingToCreate(): void
    {
        $this->assertTrue(DatabaseProvisioner::supports('mysql'));
        $this->assertTrue(DatabaseProvisioner::supports('pgsql'));
        $this->assertFalse(
            DatabaseProvisioner::supports('sqlite'),
            'a SQLite wiki owns a file already, and isolation there is the file permissions'
        );
    }

    public function testEveryWikiGetsItsOwnPassword(): void
    {
        $first = DatabaseProvisioner::generatePassword();

        $this->assertNotSame($first, DatabaseProvisioner::generatePassword());
        $this->assertGreaterThanOrEqual(
            24,
            strlen($first),
            'the password is never typed by anybody, so it may as well be long'
        );
    }

    /** A database and a user name are interpolated into DDL, which cannot be parameterised. */
    public function testANameThatIsNotAnIdentifierIsRefused(): void
    {
        foreach ([
            'wiki`; DROP DATABASE mysql; --',
            'wiki one',
            '1wiki',
            '',
            'wiki-one',
            "wiki'",
        ] as $hostile) {
            try {
                $this->provisionFor(['db_database' => $hostile]);
                $this->fail("'$hostile' was accepted as a database name");
            } catch (\Throwable $refused) {
                $this->assertStringContainsString(
                    'is not a name this can create',
                    $refused->getMessage(),
                    "'$hostile' must be refused before anything connects, not escaped"
                );
            }
        }
    }

    public function testAWikiWithNoPasswordIsNotGrantedAnything(): void
    {
        $this->expectExceptionMessageMatches('/password of its own/');

        $this->provisionFor(['db_password' => '']);
    }

    public function testAnOrdinaryNameIsAccepted(): void
    {
        $refused = false;

        try {
            $this->provisionFor(['db_database' => 'wiki_two', 'db_user' => 'wiki_two']);
        } catch (\Throwable $th) {
            $refused = str_contains($th->getMessage(), 'is not a name this can create');
        }

        $this->assertFalse($refused, 'a plain identifier must get as far as trying to connect');
    }
}
