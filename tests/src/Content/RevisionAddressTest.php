<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HealthService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** A revision is addressed by its id, which two saves in one second never share; the time stays an alias that answers with the newest of them (ticket 67). */
class RevisionAddressTest extends YesWikiTestCase
{
    private const TAG = 'RevisionAddressTestPage';

    protected function tearDown(): void
    {
        $db = self::getWiki()->services->get(DbService::class);
        $db->query('DELETE FROM' . $db->prefixTable('pages') . 'WHERE tag = ?', [self::TAG]);
        self::getWiki()->services->get(PageManager::class)->startNewRequest();
        parent::tearDown();
    }

    /** @return array{0: int, 1: int} two revisions of the page saved in the same second, oldest first */
    private function twoRevisionsInOneSecond(): array
    {
        $services = self::getWiki()->services;
        $pages = $services->get(PageManager::class);
        $pages->save(self::TAG, ['content' => 'first'], '', true);
        $pages->save(self::TAG, ['content' => 'second'], '', true);
        $db = $services->get(DbService::class);
        $table = $db->prefixTable('pages');
        $rows = $db->loadAll("SELECT id FROM {$table} WHERE tag = ? ORDER BY id", [self::TAG]);
        $db->query("UPDATE {$table} SET {$db->quoteIdentifier('time')} = ? WHERE tag = ?", ['2026-11-15 10:00:00', self::TAG]);
        $pages->startNewRequest();

        return [(int)$rows[0]['id'], (int)$rows[1]['id']];
    }

    public function testEachRevisionIsReachedByItsId(): void
    {
        [$first, $second] = $this->twoRevisionsInOneSecond();
        $pages = self::getWiki()->services->get(PageManager::class);

        $this->assertSame('first', $pages->getOne(self::TAG, $first, false, true)['body']['content'] ?? null, 'the older revision, though it shares its second');
        $this->assertSame('second', $pages->getOne(self::TAG, $second, false, true)['body']['content'] ?? null);
        $this->assertSame('second', $pages->getOne(self::TAG, '2026-11-15 10:00:00', false, true)['body']['content'] ?? null, 'the time, kept as an alias, answers with the newest');
        $this->assertNull($pages->getOne('SomeOtherPage', $first, false, true), 'an id answers only for its own tag');
    }

    public function testTheRevisionsListTheNewestFirstWithinASecond(): void
    {
        [$first, $second] = $this->twoRevisionsInOneSecond();

        $ids = array_map(static fn (array $revision): int => (int)$revision['id'], self::getWiki()->services->get(PageManager::class)->getRevisions(self::TAG));

        $this->assertSame([$second, $first], $ids);
    }

    public function testARequestAsksForARevisionByIdBeforeTime(): void
    {
        $this->assertSame(12, PageManager::revisionAsked('12', '2026-11-15 10:00:00'));
        $this->assertSame('2026-11-15 10:00:00', PageManager::revisionAsked(null, '2026-11-15 10:00:00'));
        $this->assertSame('2026-11-15 10:00:00', PageManager::revisionAsked('abc', '2026-11-15 10:00:00'));
        $this->assertNull(PageManager::revisionAsked('', ''));
    }

    public function testTwoCurrentRowsForOneTagAreReported(): void
    {
        $services = self::getWiki()->services;
        $services->get(PageManager::class)->save(self::TAG, ['content' => 'first'], '', true);
        $health = $services->get(HealthService::class);
        $health->startNewRequest();
        $this->assertNotSame(self::TAG, $health->run('tags-with-two-current-rows')?->detail);

        $db = $services->get(DbService::class);
        $db->query('UPDATE' . $db->prefixTable('pages') . "SET latest = 'Y' WHERE tag = ?", [self::TAG]);
        $services->get(PageManager::class)->save(self::TAG, ['content' => 'second'], '', true);
        $db->query('UPDATE' . $db->prefixTable('pages') . "SET latest = 'Y' WHERE tag = ?", [self::TAG]);
        $health->startNewRequest();

        $this->assertStringContainsString(self::TAG, (string)$health->run('tags-with-two-current-rows')?->detail, 'ADR-0001 broken by a raw write is shown, not discovered by a failing sign-in');
    }
}
