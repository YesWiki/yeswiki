<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueSchema;

require_once 'src/migrations/20261023120000_TheQueueHasItsTables.php';

/** A wiki that predates the queue gets its table and the Journal's index; one that has them already keeps what is in them. Run on a database of its own. */
class TheQueueHasItsTablesTest extends TestCase
{
    private string $file;
    private DbService $db;
    private QueueSchema $queue;
    private JournalSchema $journal;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-migration-') . '.db';
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->queue = new QueueSchema($this->db);
        $this->journal = new JournalSchema($this->db);
        $this->journal->create();
    }

    protected function tearDown(): void
    {
        unset($this->queue, $this->journal, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testTheTableIsCreatedAndTheJournalGetsItsIndex(): void
    {
        $name = $this->journal->table() . '_idx_channel_target_at';
        $this->db->query('DROP INDEX ' . $this->db->quoteIdentifier($name));
        $this->assertFalse($this->queue->exists());
        $this->assertFalse($this->db->schema()->indexExists('journal', $name), 'a Journal made before the queue does not have it');

        $this->migrate();

        $this->assertTrue($this->queue->exists());
        $this->assertTrue($this->db->schema()->indexExists('journal', $name));
    }

    /** Run again, or on a wiki installed with the table: the messages it holds are still there. */
    public function testRunningAgainLeavesTheMessagesAlone(): void
    {
        $this->migrate();
        $table = $this->db->quoteIdentifier($this->queue->table());
        $this->db->query(
            "INSERT INTO {$table} (queue_name, message_type, body, headers, created_at, available_at) VALUES (?, ?, ?, ?, ?, ?)",
            ['default', 'Some\\Message', '{}', '[]', '2026-10-23 12:00:00', '2026-10-23 12:00:00']
        );

        $this->migrate();

        $this->assertSame(1, (int)$this->db->scalar("SELECT COUNT(*) FROM {$table}"));
    }

    private function migrate(): void
    {
        $migration = new \TheQueueHasItsTables();
        $migration->setServices(new ServiceLocator([
            QueueSchema::class => fn (): QueueSchema => $this->queue,
            JournalSchema::class => fn (): JournalSchema => $this->journal,
        ]));
        $migration->run();
    }
}
