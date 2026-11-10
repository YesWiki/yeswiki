<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Lock\DatabaseLocks;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A long-running process outlives its connection: the server drops it, DbService opens another, and nothing runs twice or unlocked. */
#[CoversClass(DbService::class)]
#[CoversClass(DatabaseLocks::class)]
class DbServiceReconnectTest extends YesWikiTestCase
{
    private ParameterBagInterface $params;

    private DbService $killer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->params = self::getWiki()->services->get(ParameterBagInterface::class);
        if ($this->params->get('db_driver') === 'sqlite') {
            $this->markTestSkipped('SQLite has no server to drop a connection');
        }
        $this->killer = new DbService($this->params);
    }

    public function testAReadAfterTheServerDroppedTheConnectionIsAnswered(): void
    {
        $db = new DbService($this->params);
        $before = $this->connectionOf($db);
        $this->drop($before);

        $this->assertSame(1, (int)$db->scalar('SELECT 1'));
        $this->assertNotSame($before, $this->connectionOf($db), 'on a connection of its own');
    }

    public function testAWriteRunsOnceAfterTheConnectionIsDropped(): void
    {
        $table = trim($this->killer->prefixTable('reconnect_probe'));
        $this->killer->query("CREATE TABLE {$table} (n INT)");
        try {
            $db = new DbService($this->params);
            $this->drop($this->connectionOf($db));

            try {
                $db->query("INSERT INTO {$table} (n) VALUES (1)");
                $replayed = true;
            } catch (\Exception) {
                $replayed = false;
            }

            $rows = (int)$this->killer->scalar("SELECT COUNT(*) FROM {$table}");
            if ($this->params->get('db_driver') === 'mysql') {
                $this->assertTrue($replayed, 'MySQL says the statement never left, so it is sent again');
                $this->assertSame(1, $rows);
            } else {
                $this->assertFalse($replayed, 'PostgreSQL cannot say whether it ran, so a write is not sent again');
                $this->assertSame(0, $rows);
            }
            $db->query("INSERT INTO {$table} (n) VALUES (2)");
            $this->assertSame($replayed ? 2 : 1, (int)$this->killer->scalar("SELECT COUNT(*) FROM {$table}"), 'and the next statement runs');
        } finally {
            $this->killer->query("DROP TABLE {$table}");
        }
    }

    public function testTheLocksOfTheDroppedConnectionAreTakenBack(): void
    {
        $db = new DbService($this->params);
        $locks = new DatabaseLocks($db, $this->params);
        $name = 'reconnect-' . bin2hex(random_bytes(4));
        $this->assertTrue($locks->tryAcquire($name));

        $this->drop($this->connectionOf($db));
        $db->scalar('SELECT 1');

        $elsewhere = new DatabaseLocks($this->killer, $this->params);
        $this->assertTrue($elsewhere->probe($name), 'the lock is held again, by the new connection');
        $heldElsewhere = false;
        $this->assertFalse($elsewhere->tryAcquire($name, $heldElsewhere));
        $locks->release($name);
        $this->assertFalse($elsewhere->probe($name));
    }

    public function testALockSomebodyElseTookMeanwhileFailsTheStatement(): void
    {
        $db = new DbService($this->params);
        $locks = new DatabaseLocks($db, $this->params);
        $name = 'reconnect-' . bin2hex(random_bytes(4));
        $this->assertTrue($locks->tryAcquire($name));

        $this->drop($this->connectionOf($db));
        $usurper = new DatabaseLocks($this->killer, $this->params);
        $this->assertTrue($usurper->tryAcquire($name), 'the dropped connection let it go');

        try {
            $db->scalar('SELECT 1');
            $this->fail('a process that lost its lock must not carry on as though it held it');
        } catch (\Exception $refused) {
            $this->assertStringContainsString('SELECT 1', $refused->getMessage());
        }
        $locks->release($name);
        $this->assertTrue($usurper->probe($name), 'and letting go of a lock it lost does not free the usurper\'s');
        $usurper->release($name);
        $this->assertSame(1, (int)$db->scalar('SELECT 1'), 'the new connection serves what comes next');
    }

    public function testATransactionIsNotReopenedBehindItsBack(): void
    {
        $db = new DbService($this->params);
        $db->beginTransaction();
        $this->drop($this->connectionOf($db));

        try {
            $db->scalar('SELECT 1');
            $this->fail('the work of a transaction whose connection went is gone with it');
        } catch (\Exception) {
        }
        $db->rollBack();

        $this->assertFalse($db->inTransaction());
        $this->assertSame(1, (int)$db->scalar('SELECT 1'));
    }

    private function connectionOf(DbService $db): int
    {
        return (int)$db->scalar($this->params->get('db_driver') === 'pgsql' ? 'SELECT pg_backend_pid()' : 'SELECT CONNECTION_ID()');
    }

    private function drop(int $connection): void
    {
        if ($this->params->get('db_driver') === 'pgsql') {
            $this->killer->scalar('SELECT pg_terminate_backend(CAST(? AS integer))', null, [$connection]);
        } else {
            $this->killer->query('KILL ' . $connection);
        }
        usleep(200000);
    }
}
