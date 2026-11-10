<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Service\FormManager;
use YesWiki\Identity\Message\PurgeSessions;
use YesWiki\Identity\Message\PurgeSessionsHandler;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\SessionSchema;
use YesWiki\Kernel\Session\DatabaseSessions;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 02: a session is a row of the wiki's database, so any replica serving the next request finds what the last one wrote. */
#[CoversClass(DatabaseSessions::class)]
#[CoversClass(SessionSchema::class)]
#[CoversClass(PurgeSessionsHandler::class)]
class DatabaseSessionsTest extends YesWikiTestCase
{
    /** @var array<string, mixed> */
    private array $config;

    private DbService $db;

    /** @var list<string> */
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $services = self::getWiki()->services;
        $this->config = $services->get(ParameterBagInterface::class)->all();
        $this->db = $services->get(DbService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->ids as $id) {
            $this->db->query('DELETE FROM' . $this->db->prefixTable(SessionSchema::TABLE) . 'WHERE sess_id = ?', [$id]);
        }
        parent::tearDown();
    }

    public function testASessionOneProcessWroteIsReadByAnother(): void
    {
        $id = $this->id();
        $this->assertSame('', $this->inAnotherProcess($id, 'write', 'a token for the next request'));

        $this->assertSame('a token for the next request', $this->inAnotherProcess($id, 'read'));
        $this->assertSame(1, (int)$this->db->scalar('SELECT COUNT(*) FROM' . $this->db->prefixTable(SessionSchema::TABLE) . 'WHERE sess_id = ?', 0, [$id]));
    }

    public function testATableNotCreatedYetIsCreatedByTheFirstSession(): void
    {
        $config = ['table_prefix' => 'zzsess' . bin2hex(random_bytes(3)) . '_'] + $this->config;
        $sessions = new DatabaseSessions($config);
        try {
            $this->assertTrue($sessions->write($this->id(), 'data'));
            $this->assertSame('data', $sessions->read($this->ids[0]));
            $sessions->close();
        } finally {
            $this->db->query('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier($config['table_prefix'] . SessionSchema::TABLE));
        }
    }

    public function testABackupLeavesTheSessionsOut(): void
    {
        $prefix = (string)$this->config['table_prefix'];

        $kept = DumpRewriter::contentTables([$prefix . 'pages', $prefix . SessionSchema::TABLE, $prefix . 'triples'], $prefix);

        $this->assertNotContains($prefix . SessionSchema::TABLE, $kept, 'a restored session is a stolen one');
        $this->assertContains($prefix . 'pages', $kept);
    }

    public function testThePurgeForgetsOnlyTheSessionsThatExpired(): void
    {
        $table = $this->db->prefixTable(SessionSchema::TABLE);
        [$gone, $alive] = [$this->id(), $this->id()];
        $this->db->query("INSERT INTO {$table} (sess_id, sess_data, sess_lifetime, sess_time) VALUES (?, ?, ?, ?)", [$gone, 'x', time() - 60, time() - 3600]);
        $this->db->query("INSERT INTO {$table} (sess_id, sess_data, sess_lifetime, sess_time) VALUES (?, ?, ?, ?)", [$alive, 'x', time() + 3600, time()]);

        self::getWiki()->services->get(PurgeSessionsHandler::class)(new PurgeSessions());

        $left = $this->db->loadAll("SELECT sess_id FROM {$table} WHERE sess_id IN (?, ?)", [$gone, $alive]);
        $this->assertSame([$alive], array_column($left, 'sess_id'));
    }

    public function testAFieldKnowsItsFormWithoutTheSession(): void
    {
        $forms = self::getWiki()->services->get(FormManager::class);
        $form = $forms->getAll()[array_key_first($forms->getAll())] ?? null;
        if (!is_array($form)) {
            $this->markTestSkipped('this wiki has no form');
        }

        foreach ($forms->prepareData($form) as $field) {
            $this->assertSame((string)$form['id'], (new \ReflectionProperty($field, 'formId'))->getValue($field));
        }
    }

    private function id(): string
    {
        return $this->ids[] = 'test' . bin2hex(random_bytes(12));
    }

    /** What a separate PHP process, with the wiki's session handler, writes to or reads from that session. */
    private function inAnotherProcess(string $id, string $do, string $value = ''): string
    {
        $configFile = sys_get_temp_dir() . '/yw-sess-' . bin2hex(random_bytes(6));
        file_put_contents($configFile, serialize($this->config));
        $code = 'define("YESWIKI_INSTANCE_DIR", ' . var_export(YESWIKI_INSTANCE_DIR, true) . '); require "vendor/autoload.php";'
            . ' session_set_save_handler(new YesWiki\Kernel\Session\DatabaseSessions(unserialize(file_get_contents($argv[1]))), true);'
            . ' session_id($argv[2]); session_start();'
            . ' if ($argv[3] === "write") { $_SESSION["handed"] = $argv[4]; } else { echo $_SESSION["handed"] ?? "(nothing)"; }'
            . ' session_write_close();';
        $output = (string)shell_exec(implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-r', $code, '--', $configFile, $id, $do, $value])) . ' 2>&1');
        unlink($configFile);

        return trim($output);
    }
}
