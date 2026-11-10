<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\Lock\Locks;
use YesWiki\Kernel\Service\MigrationService;
use YesWiki\Kernel\Service\Probes;
use YesWiki\Kernel\Service\Replication;
use YesWiki\Kernel\Service\SchemaGate;
use YesWiki\Kernel\Service\SchemaStep;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 06: migrations run one at a time, and a replica whose code and database disagree refuses writes and says so to its readiness probe. */
#[CoversClass(SchemaStep::class)]
#[CoversClass(SchemaGate::class)]
#[CoversClass(MigrationService::class)]
class SchemaStepTest extends YesWikiTestCase
{
    private const SHIPPED = '99990101000000_SchemaStepProbe';

    private string $folder = '';

    private string $markerWas = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/yw-schema-step-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->folder . 'migrations', 0o755, true);
        file_put_contents($this->folder . 'migrations/' . self::SHIPPED . '.php', '<?php');
        $db = $this->db();
        $this->markerWas = (string)$db->scalar('SELECT value FROM' . $db->prefixTable('triples') . 'WHERE resource = ? AND property = ?', '', [SchemaStep::RESOURCE, SchemaStep::LATEST_CORE]);
    }

    protected function tearDown(): void
    {
        putenv(Replication::ENV);
        $db = $this->db();
        $table = $db->prefixTable('triples');
        $db->query("DELETE FROM {$table} WHERE resource LIKE ?", ['%' . self::SHIPPED]);
        $db->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [SchemaStep::RESOURCE, SchemaStep::LATEST_CORE]);
        if ($this->markerWas !== '') {
            $db->query("INSERT INTO {$table} (resource, property, value) VALUES (?, ?, ?)", [SchemaStep::RESOURCE, SchemaStep::LATEST_CORE, $this->markerWas]);
        }
        @unlink($this->folder . 'migrations/' . self::SHIPPED . '.php');
        @rmdir($this->folder . 'migrations');
        @rmdir($this->folder);
        parent::tearDown();
    }

    public function testACodeWhoseMigrationHasNotRunIsBehindThenInStep(): void
    {
        $step = new SchemaStep($this->db());

        $this->assertSame(SchemaStep::BEHIND, $step->state([$this->folder]));

        self::getWiki()->services->get(TripleStore::class)->create(self::SHIPPED, TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID, '', '');
        $this->assertSame(SchemaStep::IN_STEP, $step->state([$this->folder]), 'once the Job ran it');
    }

    public function testADatabaseMigratedByNewerCodeIsAhead(): void
    {
        $step = new SchemaStep($this->db());
        self::getWiki()->services->get(TripleStore::class)->create(self::SHIPPED, TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID, '', '');
        $step->record($this->folder);
        $this->assertSame(SchemaStep::IN_STEP, $step->state([$this->folder]));

        $older = sys_get_temp_dir() . '/yw-schema-step-older-' . bin2hex(random_bytes(4)) . '/';
        mkdir($older . 'migrations', 0o755, true);
        file_put_contents($older . 'migrations/20200101000000_' . self::SHIPPED . '.php', '<?php');
        self::getWiki()->services->get(TripleStore::class)->create('20200101000000_' . self::SHIPPED, TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID, '', '');
        try {
            $this->assertSame(SchemaStep::AHEAD, $step->state([$older]), 'the database ran a core migration this code does not ship, newer than all it does');
        } finally {
            unlink($older . 'migrations/20200101000000_' . self::SHIPPED . '.php');
            rmdir($older . 'migrations');
            rmdir($older);
        }
    }

    public function testTheMigrationsAReleaseDeletedMakeNobodyAhead(): void
    {
        $step = new SchemaStep($this->db());
        $table = $this->db()->prefixTable('triples');
        $this->db()->query("DELETE FROM {$table} WHERE resource = ? AND property = ?", [SchemaStep::RESOURCE, SchemaStep::LATEST_CORE]);
        self::getWiki()->services->get(TripleStore::class)->create('20200101000000_' . self::SHIPPED, TripleStore::TYPE_URI, MigrationService::TRIPLES_MIGRATION_ID, '', '');

        $step->record(YESWIKI_PROGRAM_DIR . '/src/');

        $this->assertSame(SchemaStep::IN_STEP, $step->state(self::getWiki()->services->get(SchemaGate::class)->folders()), 'a migration the database ran and no code ships any more is history, not a newer release');
    }

    public function testOnAReplicaOutOfStepAWriteWaitsAndAReadGoesOn(): void
    {
        $gate = new SchemaGate(new class($this->db()) extends SchemaStep {
            public function state(array $folders): string
            {
                return SchemaStep::BEHIND;
            }
        }, new ExtensionRegistry());

        $this->assertNull($gate->refusal(Request::create('/?PagePrincipale', 'POST')), 'a single machine is never refused');

        putenv(Replication::ENV . '=1');
        $this->assertNull($gate->refusal(Request::create('/?PagePrincipale')), 'a page view is served');
        $this->assertSame(_t('SCHEMA_BEHIND'), $gate->refusal(Request::create('/?PagePrincipale/edit', 'POST')), 'a page save is not');
        $this->assertSame(_t('SCHEMA_BEHIND'), $gate->refusal(Request::create('/?PagePrincipale/deletepage&' . RequestGuard::FIELD . '=x')), 'nor a signed link that changes something');
    }

    public function testAMigrateWaitsForTheOneRunningAndLetsGoOfTheLock(): void
    {
        $locks = new class implements Locks {
            public int $refusals = 2;

            public int $tries = 0;

            public function tryAcquire(string $name, bool &$heldElsewhere = false): bool
            {
                ++$this->tries;
                $heldElsewhere = $this->refusals-- > 0;

                return !$heldElsewhere;
            }

            public function release(string $name): void
            {
            }

            public function probe(string $name): ?bool
            {
                return null;
            }

            public function forget(string $name): void
            {
            }
        };
        $wait = new \ReflectionMethod(MigrationService::class, 'waitFor');

        $this->assertTrue($wait->invoke(self::getWiki()->services->get(MigrationService::class), $locks));
        $this->assertSame(3, $locks->tries, 'it asked again while another run held the lock');

        $locks->refusals = 0;
        $real = self::getWiki()->services->get(Locks::class);
        self::getWiki()->services->get(MigrationService::class)->run();
        $heldElsewhere = false;
        $this->assertTrue($real->tryAcquire(MigrationService::LOCK, $heldElsewhere), 'run() let go of the lock');
        $real->release(MigrationService::LOCK);
    }

    public function testTheReadinessProbeSaysWhetherTheMigrationsRan(): void
    {
        putenv(Replication::ENV . '=1');
        ob_start();
        Probes::ready();
        $answer = json_decode((string)ob_get_clean(), true);

        $this->assertIsArray($answer);
        $this->assertSame('ok', $answer['checks']['migrations'] ?? null, 'this wiki ran every migration it ships');
    }

    private function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }
}
