<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Command\DestroyCommand;
use YesWiki\Admin\Service\DatabaseProvisioner;

#[CoversMethod(DatabaseProvisioner::class, 'destroy')]
class DestroyingAWikiTest extends TestCase
{
    /** @param array<string, mixed> $overrides */
    private function destroyFor(array $overrides = [], string $adminUser = 'root'): void
    {
        (new DatabaseProvisioner())->destroy($adminUser, 'secret', array_merge([
            'db_driver' => 'mysql',
            'db_host' => '127.0.0.1',
            'db_port' => '1',
            'db_database' => 'wiki_one',
            'db_user' => 'wiki_one',
        ], $overrides));
    }

    public function testTheServersOwnDatabasesAreNotAWikisToDrop(): void
    {
        foreach (DatabaseProvisioner::NEVER_DROP as $theirs) {
            try {
                $this->destroyFor(['db_database' => $theirs]);
                $this->fail("$theirs was accepted as a database to drop");
            } catch (\Throwable $refused) {
                $this->assertStringContainsString(
                    "the server's own database",
                    $refused->getMessage(),
                    "$theirs must be refused before anything connects"
                );
            }
        }
    }

    /** A wiki installed with the administrator's own credentials has no account of its own, and dropping that account would take every other wiki on the server with it. */
    public function testTheAdministratorsOwnAccountIsNeverDropped(): void
    {
        $this->expectExceptionMessageMatches('/account doing the dropping/');

        $this->destroyFor(['db_user' => 'root'], 'root');
    }

    public function testANameThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectExceptionMessageMatches('/is not a name this can create/');

        $this->destroyFor(['db_database' => 'wiki`; DROP DATABASE mysql; --']);
    }

    public function testTheWikiIsNamedByItsHostWhateverShapeTheBaseUrlIsIn(): void
    {
        foreach ([
            'https://wiki.example.org/?' => 'wiki.example.org',
            'http://wiki.example.org/sub/?' => 'wiki.example.org',
            'wiki.example.org/?' => 'wiki.example.org',
            'https://WIKI.example.org:8443/?' => 'wiki.example.org',
            '' => '',
        ] as $stated => $expected) {
            $this->assertSame($expected, DestroyCommand::hostOf($stated), "base_url '$stated'");
        }
    }
}
