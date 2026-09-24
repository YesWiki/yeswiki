<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 04: worker mode puts several threads on one SQLite file, so it needs WAL and a busy timeout. */
#[CoversMethod(DbService::class, 'initSqlConnection')]
#[CoversMethod(DbService::class, 'beginTransaction')]
class SqliteSurvivesConcurrentThreadsTest extends YesWikiTestCase
{
    public function testASqliteWikiIsInWalModeWithABusyTimeout(): void
    {
        $wiki = $this->getWiki();
        $db = $wiki->services->get(DbService::class);

        if ($db->getDriver() !== 'sqlite') {
            $this->markTestSkipped('this wiki is not on SQLite');
        }

        $journalMode = $db->loadSingle('PRAGMA journal_mode');
        $this->assertNotNull($journalMode);
        $this->assertSame('wal', strtolower((string)reset($journalMode)));

        $busyTimeout = $db->loadSingle('PRAGMA busy_timeout');
        $this->assertNotNull($busyTimeout);
        $this->assertGreaterThan(
            0,
            (int)reset($busyTimeout),
            'without a busy timeout a second thread gets SQLITE_BUSY instead of waiting'
        );
    }

    /** A transaction that reads before it writes must wait for the other writer, not fail on the spot. */
    public function testATransactionThatReadsFirstStillWritesAfterAnotherConnectionCommitted(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'yw-busy-') . '.db';
        $open = static fn (): DbService => new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $file,
            'table_prefix' => 'busy_',
            'debug' => false,
        ]));
        $mine = $open();
        $other = $open();

        try {
            $mine->query('CREATE TABLE busy_rows (tag TEXT)');
            $other->query('PRAGMA busy_timeout = 0');

            $mine->transactional(function () use ($mine, $other): void {
                $mine->loadAll('SELECT tag FROM busy_rows');
                try {
                    $other->query("INSERT INTO busy_rows (tag) VALUES ('theirs')");
                } catch (\Exception $waitingForMine) {
                }
                $mine->query("INSERT INTO busy_rows (tag) VALUES ('mine')");
            });

            $this->assertContains('mine', array_column($mine->loadAll('SELECT tag FROM busy_rows'), 'tag'));
        } finally {
            unset($mine, $other);
            foreach (['', '-wal', '-shm'] as $suffix) {
                @unlink($file . $suffix);
            }
        }
    }
}
