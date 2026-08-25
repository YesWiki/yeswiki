<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Entity\JournalChannel;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 51 / ADR-0025: the two sinks, the collapse, and the retention. */
class JournalTest extends YesWikiTestCase
{
    /** Everything this test writes is under one action prefix, so the sweep at the end is exact. */
    private const FIXTURE = 'journaltest';

    private static function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    private static function table(): string
    {
        $db = self::db();

        return $db->quoteIdentifier(self::getWiki()->services->get(JournalSchema::class)->table());
    }

    protected function setUp(): void
    {
        $this->sweep();
    }

    protected function tearDown(): void
    {
        $this->sweep();
    }

    private function sweep(): void
    {
        $db = self::db();
        $table = self::table();
        $db->query(
            "DELETE FROM {$table} WHERE {$db->quoteIdentifier('action')} LIKE ?",
            [self::FIXTURE . '%']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(string $action): array
    {
        $db = self::db();

        return $db->loadAll(
            'SELECT * FROM ' . self::table() . ' WHERE ' . $db->quoteIdentifier('action') . ' = ?',
            [$action]
        );
    }

    /**
     * A journal pointed at a stream this test can read back, which is the only seam the sink has.
     *
     * @return array{0: Journal, 1: resource}
     */
    private function journalWritingToMemory(): array
    {
        $journal = self::getWiki()->services->get(Journal::class);
        $sink = fopen('php://memory', 'w+b');
        $this->assertNotFalse($sink);
        $journal->writeTo($sink);

        return [$journal, $sink];
    }

    private function readBack(mixed $sink): string
    {
        rewind($sink);

        return (string)stream_get_contents($sink);
    }

    public function testThreeDeletionsAreThreeRows(): void
    {
        [$journal] = $this->journalWritingToMemory();

        foreach (['PremierePage', 'DeuxiemePage', 'TroisiemePage'] as $tag) {
            $journal->audit(self::FIXTURE . '.delete', $tag);
        }

        $rows = $this->rows(self::FIXTURE . '.delete');
        $this->assertCount(3, $rows, 'an audit entry never collapses: three deletions are three facts');
        foreach ($rows as $row) {
            $this->assertSame(1, (int)$row['repeat']);
            $this->assertNull($row['fingerprint']);
        }
    }

    public function testAStormOfOneFaultIsOneRowWithACount(): void
    {
        [$journal] = $this->journalWritingToMemory();

        $failure = new \RuntimeException('the same thing, over and over');
        for ($i = 0; $i < 200; $i++) {
            $journal->error('User ' . $i . ' not found', [
                'exception' => $failure,
                'action' => self::FIXTURE . '.storm',
            ]);
        }

        $rows = $this->rows(self::FIXTURE . '.storm');
        $this->assertCount(1, $rows, 'a diagnostic collapses on (fingerprint, day)');
        $this->assertSame(200, (int)$rows[0]['repeat']);
        $this->assertSame(JournalChannel::Diagnostic->value, $rows[0]['channel']);
        $this->assertNotNull($rows[0]['fingerprint']);
        $this->assertSame(
            'User 199 not found',
            json_decode((string)$rows[0]['context'], true)['message'] ?? null,
            'the latest message is stored; the count carries the rest'
        );
    }

    /**
     * The fingerprint is over where a fault happened, never over what it said -- because messages
     * carry variable data, and fingerprinting on them defeats the dedup exactly when a storm makes
     * it matter.
     */
    public function testTwoFaultsFromDifferentPlacesAreTwoRows(): void
    {
        [$journal] = $this->journalWritingToMemory();

        $journal->error('first', ['exception' => new \RuntimeException('a'), 'action' => self::FIXTURE . '.two']);
        $journal->error('second', ['exception' => new \LogicException('b'), 'action' => self::FIXTURE . '.two']);

        $this->assertCount(2, $this->rows(self::FIXTURE . '.two'));
    }

    public function testNothingStoredOrLoggedCarriesAnArgumentValue(): void
    {
        [$journal, $sink] = $this->journalWritingToMemory();

        $secret = 'hunter2-correct-horse-battery-staple';
        try {
            (static function (string $password, array $post): void {
                throw new \RuntimeException('could not sign in');
            })($secret, ['password' => $secret]);
        } catch (\Throwable $thrown) {
            $journal->error($thrown->getMessage(), ['exception' => $thrown, 'action' => self::FIXTURE . '.secret']);
        }

        $rows = $this->rows(self::FIXTURE . '.secret');
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString($secret, (string)$rows[0]['context']);
        $this->assertStringNotContainsString($secret, $this->readBack($sink));

        $frames = json_decode((string)$rows[0]['context'], true)['frames'] ?? [];
        $this->assertNotEmpty($frames, 'the trace is kept -- as types');
        $this->assertStringContainsString('string', implode("\n", $frames));
    }

    public function testEveryLineOnTheStreamNamesItsWiki(): void
    {
        [$journal, $sink] = $this->journalWritingToMemory();

        $journal->audit(self::FIXTURE . '.stream', 'PagePrincipale');

        $lines = array_values(array_filter(explode("\n", $this->readBack($sink))));
        $this->assertNotEmpty($lines);
        $decoded = json_decode((string)end($lines), true);
        $this->assertIsArray($decoded);
        $this->assertNotSame('', $decoded['wiki'] ?? '', 'a farm serves many wikis down one stream');
        $this->assertSame(JournalChannel::Audit->value, $decoded['channel']);
        $this->assertSame(self::FIXTURE . '.stream', $decoded['action']);
    }

    public function testTheWikiIdentifierKeepsTwoWikisOnOneHostApart(): void
    {
        $this->assertSame('example.org/wiki1', Journal::wikiIdentifier('https://example.org/wiki1'));
        $this->assertSame('example.org/wiki2', Journal::wikiIdentifier('http://example.org/wiki2/'));
        $this->assertSame('example.org', Journal::wikiIdentifier('https://example.org'));
    }

    public function testPruningRemovesWhatIsPastItsRetentionAndLeavesTheRest(): void
    {
        $db = self::db();
        $table = self::table();
        $columns = implode(', ', array_map(
            fn (string $column): string => $db->quoteIdentifier($column),
            ['at', 'last_at', 'repeat', 'channel', 'level', 'actor', 'action', 'target']
        ));

        foreach ([
            ['-400 days', JournalChannel::Audit, self::FIXTURE . '.old'],
            ['-10 days', JournalChannel::Audit, self::FIXTURE . '.recent'],
        ] as [$offset, $channel, $action]) {
            $at = date('Y-m-d H:i:s', (int)strtotime($offset));
            $db->query(
                "INSERT INTO {$table} ({$columns}) VALUES (?, ?, 1, ?, 'info', 'someone', ?, '')",
                [$at, $at, $channel->value, $action]
            );
        }

        self::getWiki()->services->get(Journal::class)->prune();

        $this->assertCount(0, $this->rows(self::FIXTURE . '.old'), 'past journal_audit_purge_time');
        $this->assertCount(1, $this->rows(self::FIXTURE . '.recent'), 'and the rest is left alone');
    }
}
