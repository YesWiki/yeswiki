<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ServiceLocator;
use YesWiki\Kernel\Command\JournalPruneCommand;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Message\PruneJournal;
use YesWiki\Kernel\Message\PruneJournalHandler;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The retention is applied by the scheduled task and by `journal:prune`, both over a Journal of their own here. */
#[CoversClass(PruneJournalHandler::class)]
#[CoversClass(JournalPruneCommand::class)]
class JournalPruneTest extends YesWikiTestCase
{
    private string $file;
    private DbService $db;
    private JournalSchema $schema;
    private RuntimeConfig $config;
    private ServiceLocator $services;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'yw-journal-') . '.db';
        $this->db = new DbService(new ParameterBag(['db_driver' => 'sqlite', 'db_database' => $this->file, 'table_prefix' => 'ywj_', 'debug' => false]));
        $this->schema = new JournalSchema($this->db);
        $this->schema->create();
        $this->config = new RuntimeConfig();
        $this->services = new ServiceLocator([
            DbService::class => fn (): DbService => $this->db,
            JournalSchema::class => fn (): JournalSchema => $this->schema,
            RuntimeConfig::class => fn (): RuntimeConfig => $this->config,
            Journal::class => fn (): Journal => new Journal($this->services),
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->services, $this->config, $this->schema, $this->db);
        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->file . $suffix);
        }
    }

    public function testTheScheduledTaskPrunesTheJournal(): void
    {
        $this->row(JournalChannel::Audit, 'page.old', 400);
        $this->row(JournalChannel::Audit, 'page.recent', 10);
        $this->row(JournalChannel::Diagnostic, 'oops.old', 15);
        $this->row(JournalChannel::Queue, 'queue.run.handled', 8);

        (new PruneJournalHandler($this->services->get(Journal::class)))(new PruneJournal());

        $this->assertSame(['page.recent'], $this->actions(), 'what outlived its retention is gone, the rest is left alone');
    }

    public function testTheCommandPrunesAndSaysWhatItKept(): void
    {
        $this->row(JournalChannel::Audit, 'page.old', 400);
        $this->row(JournalChannel::Audit, 'page.recent', 10);
        $this->row(JournalChannel::Queue, 'queue.run.handled', 4);

        $tester = new CommandTester(new JournalPruneCommand($this->services));
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('1 entries removed (audit kept 365 days, diagnostics 14, queue 7)', $tester->getDisplay());
        $this->assertSame(['page.recent', 'queue.run.handled'], $this->actions());

        $this->config[Journal::QUEUE_PURGE_SETTING] = 3;
        $this->config[Journal::AUDIT_PURGE_SETTING] = 5;
        $tester->execute([]);

        $this->assertStringContainsString('2 entries removed (audit kept 5 days, diagnostics 14, queue 3)', $tester->getDisplay());
        $this->assertSame([], $this->actions(), 'the retention it prints is the one it applied');
    }

    private function row(JournalChannel $channel, string $action, int $daysAgo): void
    {
        $at = gmdate('Y-m-d H:i:s', time() - $daysAgo * 86400);
        $columns = implode(', ', array_map($this->db->quoteIdentifier(...), ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target']));
        $this->db->query(
            "INSERT INTO {$this->schema->table()} ({$columns}) VALUES (?, ?, 1, ?, 'info', '', ?, '')",
            [$at, $at, $channel->value, $action]
        );
    }

    /** @return list<string> */
    private function actions(): array
    {
        return array_column($this->db->loadAll("SELECT action FROM {$this->schema->table()} ORDER BY action"), 'action');
    }
}
