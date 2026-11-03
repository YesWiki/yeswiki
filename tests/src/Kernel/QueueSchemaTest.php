<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Queue\QueueSchema;

/** The queue's table is created at install time and by migration, on a database the wiki owns. */
#[CoversClass(QueueSchema::class)]
class QueueSchemaTest extends TestCase
{
    private string $file;
    private DbService $db;
    private QueueSchema $schema;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-queue-') . '.db';
        $this->db = new DbService(new ParameterBag([
            'db_driver' => 'sqlite',
            'db_database' => $this->file,
            'table_prefix' => 'ywq_',
            'debug' => false,
        ]));
        $this->schema = new QueueSchema($this->db);
    }

    protected function tearDown(): void
    {
        unset($this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testTheTableCarriesThePrefixWithoutPadding(): void
    {
        $this->assertSame('ywq_queue', $this->schema->table());
    }

    /** One table, whatever the queue holds: its messages alone. */
    public function testCreateThenDrop(): void
    {
        $this->assertFalse($this->schema->exists());

        $this->schema->create();
        $this->assertTrue($this->schema->exists());
        $this->assertSame(['ywq_queue'], $this->queueTables());

        $this->schema->drop();
        $this->assertFalse($this->schema->exists());
        $this->assertSame([], $this->queueTables());
    }

    public function testCreateIsIdempotent(): void
    {
        $this->schema->create();
        $this->schema->create();

        $this->assertTrue($this->schema->exists());
    }

    /** A message gets its defaults, and the table has no column nor index for a task: the Journal keeps those. */
    public function testTheTableAcceptsItsRows(): void
    {
        $this->schema->create();
        $table = $this->db->quoteIdentifier($this->schema->table());
        $insert = "INSERT INTO {$table} (queue_name, message_type, body, headers, created_at, available_at) VALUES (?, ?, ?, ?, ?, ?)";

        foreach (['default', 'failed'] as $queue) {
            $this->db->query($insert, [$queue, 'Some\\Message', '{}', '[]', '2026-10-23 12:00:00', '2026-10-23 12:00:00']);
        }
        $message = $this->db->loadSingle("SELECT * FROM {$table} WHERE queue_name = 'default'");
        $this->assertIsArray($message);
        $this->assertSame(0, (int)$message['attempts']);
        $this->assertNull($message['delivered_at']);
        $this->assertSame(
            ['id', 'queue_name', 'message_type', 'body', 'headers', 'dedup_key', 'created_at', 'available_at', 'delivered_at', 'delivered_by', 'delivered_mode', 'delivered_limit', 'attempts', 'last_error', 'killed_in'],
            array_keys($message)
        );
        $this->assertSame([], array_filter(
            $this->db->loadAll("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ?", [$this->schema->table()]),
            static fn (array $index): bool => str_contains((string)$index['name'], 'task')
        ));
    }

    /** @return list<string> the tables of this wiki whose name starts like the queue's */
    private function queueTables(): array
    {
        return array_values(array_filter(
            $this->db->schema()->getTables(),
            static fn (string $table): bool => str_starts_with($table, 'ywq_queue')
        ));
    }
}
