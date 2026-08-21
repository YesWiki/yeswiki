<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 04: worker mode puts several threads on one SQLite file, so it needs WAL and a busy timeout. */
#[CoversMethod(DbService::class, 'initSqlConnection')]
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
}
