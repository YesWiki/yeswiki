<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Render\Service\TemplateOverrides;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 18: the overrides an admin kept in custom/templates/ move to the database, but never an operator's read-only folder nor a replica's. */
class TemplateOverridesMoveToTheDatabaseTest extends YesWikiTestCase
{
    private string $folder = '';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261112090000_TemplateOverridesMoveToTheDatabase.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/yw-template-migration-' . bin2hex(random_bytes(4));
        mkdir($this->folder . '/core/yw-test-migration', 0o755, true);
        file_put_contents($this->folder . '/core/yw-test-migration/kept.twig', 'kept by an admin');
    }

    protected function tearDown(): void
    {
        exec('chmod -R u+w ' . escapeshellarg($this->folder) . '; rm -rf ' . escapeshellarg($this->folder));
        self::getWiki()->services->get(TemplateOverrides::class)->delete('core/yw-test-migration/kept.twig');
        parent::tearDown();
    }

    private function migration(): \TemplateOverridesMoveToTheDatabase
    {
        $wiki = self::getWiki();
        $migration = new \TemplateOverridesMoveToTheDatabase();
        $migration->setServices($wiki->services);
        $migration->setDbService($wiki->services->get(DbService::class));
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));

        return $migration;
    }

    public function testAWritableFolderMovesToTheDatabase(): void
    {
        $migration = $this->migration();
        $migration->moveFrom($this->folder);

        $this->assertSame('kept by an admin', self::getWiki()->services->get(TemplateOverrides::class)->read('core/yw-test-migration/kept.twig'));
        $this->assertFileDoesNotExist($this->folder . '/core/yw-test-migration/kept.twig');
        $this->assertStringContainsString('1 template override(s) moved', implode("\n", $migration->notes()));
    }

    public function testAReadOnlyFolderStaysTheOperators(): void
    {
        chmod($this->folder, 0o555);
        $this->migration()->moveFrom($this->folder);

        $this->assertNull(self::getWiki()->services->get(TemplateOverrides::class)->read('core/yw-test-migration/kept.twig'));
        $this->assertFileExists($this->folder . '/core/yw-test-migration/kept.twig');
    }

    public function testAReplicasFolderStaysTheOperators(): void
    {
        $was = getenv(Replication::ENV);
        putenv(Replication::ENV . '=1');

        try {
            $this->migration()->moveFrom($this->folder);
        } finally {
            putenv($was === false ? Replication::ENV : Replication::ENV . '=' . $was);
        }

        $this->assertNull(self::getWiki()->services->get(TemplateOverrides::class)->read('core/yw-test-migration/kept.twig'));
        $this->assertFileExists($this->folder . '/core/yw-test-migration/kept.twig');
    }
}
