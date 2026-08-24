<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 08: a relative database path belongs to an Instance, not to a working directory. */
#[CoversMethod(DbService::class, 'buildDsn')]
class DatabasePathIsTheInstancesTest extends YesWikiTestCase
{
    protected function setUp(): void
    {
        self::getWiki();
    }

    /** The DSN a wiki with this `db_database` would connect with, without connecting. */
    private function dsnFor(string $dbDatabase): string
    {
        $reflection = new \ReflectionClass(DbService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $params = $reflection->getProperty('params');
        $params->setValue($service, new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $dbDatabase,
        ]));

        $driver = $reflection->getProperty('driver');
        $driver->setValue($service, 'sqlite');

        return (string)$reflection->getMethod('buildDsn')->invoke($service);
    }

    public function testARelativePathIsTheInstances(): void
    {
        $this->assertSame(
            'sqlite:' . \YESWIKI_INSTANCE_DIR . '/private/yeswiki.db',
            $this->dsnFor('private/yeswiki.db'),
            'a relative db_database is resolved by SQLite against the process working directory, '
            . 'which every wiki in a farm shares; it has to say which Instance it belongs to'
        );
    }

    public function testAnAbsolutePathIsLeftAlone(): void
    {
        $this->assertSame(
            'sqlite:/var/lib/wikis/a.db',
            $this->dsnFor('/var/lib/wikis/a.db'),
            'an operator who names an absolute path meant it'
        );
    }

    public function testTheWorkingDirectoryDoesNotDecide(): void
    {
        $before = (string)getcwd();

        try {
            chdir(sys_get_temp_dir());
            $fromElsewhere = $this->dsnFor('private/yeswiki.db');
        } finally {
            chdir($before);
        }

        $this->assertSame(
            'sqlite:' . \YESWIKI_INSTANCE_DIR . '/private/yeswiki.db',
            $fromElsewhere,
            'the same wiki must connect to the same database whatever directory the process stands in'
        );
    }

    public function testTwoWikisInOneProcessGetTheirOwnDatabase(): void
    {
        $this->assertNotSame(
            $this->dsnFor('private/one.db'),
            $this->dsnFor('private/two.db'),
            'a farm serves many Instances from one process and each has to reach its own database'
        );
    }

    /** The working directory is per process, so nothing may quietly rely on it being an Instance. */
    public function testBootstrapDoesNotChangeTheWorkingDirectory(): void
    {
        $bootstrap = (string)file_get_contents(\YESWIKI_PROGRAM_DIR . '/src/bootstrap_paths.php');

        $this->assertStringNotContainsString(
            'chdir',
            $bootstrap,
            'chdir() makes one wiki correct and every wiki after it wrong: the working directory '
            . 'is per process, so in a farm whichever Instance booted last would own it'
        );
    }
}
