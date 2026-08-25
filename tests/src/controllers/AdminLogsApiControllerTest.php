<?php

namespace YesWiki\Test\Core\Controller;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Admin\Api\AdminLogsApiController;
use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 51: what `/admin/logs` narrows by, and where its phrases come from. */
class AdminLogsApiControllerTest extends YesWikiTestCase
{
    private const FIXTURE_ACTOR = 'AdminLogsApiControllerTestActor';

    private function controller(): AdminLogsApiController
    {
        return self::getWiki()->services->get(AdminLogsApiController::class);
    }

    private function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    private function table(): string
    {
        return $this->db()->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
    }

    protected function tearDown(): void
    {
        $db = $this->db();
        $db->query(
            'DELETE FROM ' . $this->table() . ' WHERE ' . $db->quoteIdentifier('actor') . ' = ?',
            [self::FIXTURE_ACTOR]
        );
    }

    /**
     * @param array<string, string> $query
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildWhere(array $query): array
    {
        $method = new \ReflectionMethod(AdminLogsApiController::class, 'buildWhere');
        $method->setAccessible(true);

        return $method->invoke($this->controller(), $this->db(), new Request($query));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $method = new \ReflectionMethod(AdminLogsApiController::class, 'present');
        $method->setAccessible(true);

        return $method->invoke($this->controller(), $row);
    }

    private function seed(string $channel, string $action, string $at): void
    {
        $db = $this->db();
        $columns = implode(', ', array_map(
            fn (string $column): string => $db->quoteIdentifier($column),
            ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target']
        ));

        $db->query(
            'INSERT INTO ' . $this->table() . " ({$columns}) VALUES (?, ?, 1, ?, 'info', ?, ?, '')",
            [$at, $at, $channel, self::FIXTURE_ACTOR, $action]
        );
    }

    /**
     * @param array<string, string> $query
     *
     * @return list<string>
     */
    private function actionsMatching(array $query): array
    {
        [$where, $params] = $this->buildWhere($query + ['actor' => self::FIXTURE_ACTOR]);
        $db = $this->db();

        return array_map('strval', array_column(
            $db->loadAll(
                'SELECT ' . $db->quoteIdentifier('action') . ' FROM ' . $this->table()
                . " WHERE {$where} ORDER BY " . $db->quoteIdentifier('at') . ' DESC',
                $params
            ),
            'action'
        ));
    }

    public function testTheStreamIsFilterableByChannelActorAndDateRange(): void
    {
        $this->seed(JournalChannel::Audit->value, 'content.delete', '2026-03-01 10:00:00');
        $this->seed(JournalChannel::Audit->value, 'acl.change', '2026-03-05 10:00:00');
        $this->seed(JournalChannel::Diagnostic->value, 'RuntimeException', '2026-03-09 10:00:00');

        $this->assertSame(
            ['RuntimeException', 'acl.change', 'content.delete'],
            $this->actionsMatching([]),
            'newest first'
        );

        $this->assertSame(
            ['acl.change', 'content.delete'],
            $this->actionsMatching(['channel' => 'audit'])
        );

        $this->assertSame(
            ['RuntimeException'],
            $this->actionsMatching(['channel' => 'diagnostic'])
        );

        $this->assertSame(
            ['acl.change'],
            $this->actionsMatching(['from' => '2026-03-02', 'to' => '2026-03-05']),
            '"to" means the whole of that day'
        );

        $this->assertSame(
            ['content.delete'],
            $this->actionsMatching(['action' => 'content.delete'])
        );
    }

    /** Anything not on the whitelist is not a filter, and certainly not SQL. */
    public function testAnUnknownChannelOrLevelIsIgnoredRatherThanTrusted(): void
    {
        [$where, $params] = $this->buildWhere([
            'channel' => "audit' OR '1'='1",
            'level' => 'shouting',
            'from' => 'yesterday',
        ]);

        $this->assertSame('1 = 1', $where);
        $this->assertSame([], $params);
    }

    /**
     * The stored `action` is a dotted code and the phrase is built here, so a wiki that changes
     * language does not end up with a bilingual audit trail.
     */
    public function testThePhraseIsBuiltAtReadTimeFromTheActionCode(): void
    {
        $presented = $this->present(['action' => 'content.delete', 'at' => '2026-03-01 10:00:00']);

        $this->assertSame('content.delete', $presented['action']);
        $this->assertSame(_t('JOURNAL_ACTION_CONTENT_DELETE'), $presented['phrase']);
        $this->assertNotSame('content.delete', $presented['phrase']);
    }

    /** An exception class has no key of its own, and reading it raw is the right answer. */
    public function testAnActionWithNoTranslationReadsAsItself(): void
    {
        $this->assertSame(
            'RuntimeException',
            $this->present(['action' => 'RuntimeException'])['phrase']
        );
    }

    public function testALegacyEntryKeepsItsOwnSentence(): void
    {
        $presented = $this->present([
            'action' => Journal::LEGACY,
            'context' => json_encode(['message' => 'Suppression de la page ->""MichelMelanie""']),
        ]);

        $this->assertSame('Suppression de la page ->""MichelMelanie""', $presented['message']);
    }
}
