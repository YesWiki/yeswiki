<?php

namespace YesWiki\Test\Kernel\Database;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `DbService::transactional()`, and the invisible-page failure it exists to prevent. */
class TransactionsTest extends YesWikiTestCase
{
    private const TAG = 'TransactionsRegressionPage';

    private static function db(): DbService
    {
        return self::getWiki()->services->get(DbService::class);
    }

    protected function tearDown(): void
    {
        self::getWiki()->services->get(PageManager::class)->deleteOrphaned(self::TAG);
        parent::tearDown();
    }

    /** A minimal but valid `pages` row -- `time` is NOT NULL, so the fixture must supply it. */
    private function insertRow(string $tag, string $latest = 'Y'): void
    {
        $db = self::db();
        $db->query(
            'INSERT INTO ' . $db->prefixTable('pages')
            . ' (tag, ' . $db->quoteIdentifier('time') . ', latest, parent, owner, '
            . $db->quoteIdentifier('user') . ", body) VALUES (?, {$db->now()}, ?, ?, ?, ?, ?)",
            [$tag, $latest, '', '', '', '{}']
        );
    }

    /** How many `latest = 'Y'` rows a tag has -- must always be exactly one for a live page. */
    private function latestCount(string $tag): int
    {
        $db = self::db();

        return (int)$db->scalar(
            "SELECT COUNT(*) FROM {$db->prefixTable('pages')} WHERE tag = ? AND latest = 'Y'",
            0,
            [$tag]
        );
    }

    public function testWorkIsCommittedWhenItReturns(): void
    {
        $db = self::db();

        $db->transactional(function (): void {
            $this->insertRow(self::TAG);
        });

        $this->assertSame(1, $this->latestCount(self::TAG), 'a committed insert must be visible');
    }

    /** THE test: the demote-then-insert pair, with the insert failing. */
    public function testAFailedSecondStatementLeavesThePageVisible(): void
    {
        $db = self::db();
        $pages = $db->prefixTable('pages');

        $this->insertRow(self::TAG);
        $this->assertSame(1, $this->latestCount(self::TAG));

        try {
            $db->transactional(function () use ($db, $pages): void {
                $db->query("UPDATE {$pages} SET latest = 'N' WHERE tag = ?", [self::TAG]);

                $db->query("INSERT INTO {$pages} (no_such_column) VALUES (?)", ['boom']);
            });
            $this->fail('the failing statement should have propagated');
        } catch (\Throwable $expected) {
        }

        $this->assertSame(
            1,
            $this->latestCount(self::TAG),
            'the demotion must have been rolled back -- otherwise the page has no latest revision '
            . 'and silently stops existing while all its history remains'
        );
    }

    /** And the same thing through the real write path. */
    public function testARolledBackScopeLeavesNoHalfRevision(): void
    {
        $db = self::db();
        $pageManager = self::getWiki()->services->get(PageManager::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'first'], '', true);
        $this->assertSame(1, $this->latestCount(self::TAG));

        $before = (int)$db->scalar("SELECT COUNT(*) FROM {$db->prefixTable('pages')} WHERE tag = ?", 0, [self::TAG]);

        try {
            $db->transactional(function () use ($db): void {
                $db->query("UPDATE {$db->prefixTable('pages')} SET latest = 'N' WHERE tag = ?", [self::TAG]);

                throw new \RuntimeException('interrupted');
            });
        } catch (\RuntimeException $expected) {
            $this->assertSame('interrupted', $expected->getMessage());
        }

        $this->assertSame(1, $this->latestCount(self::TAG), 'still exactly one latest revision');
        $this->assertSame(
            $before,
            (int)$db->scalar("SELECT COUNT(*) FROM {$db->prefixTable('pages')} WHERE tag = ?", 0, [self::TAG]),
            'and no revision added or removed'
        );
    }

    /**
     * Nesting has to work, because these writes really do nest: AclService::writeMetadataAcls() and PageManager::save() both revision a row and either is reachable from inside the other.
     */
    public function testScopesNestWithoutStartingASecondTransaction(): void
    {
        $db = self::db();

        $db->transactional(function () use ($db): void {
            $this->assertTrue($db->inTransaction());
            $db->transactional(function () use ($db): void {
                $this->assertTrue($db->inTransaction(), 'an inner scope joins the outer one');
                $this->insertRow(self::TAG);
            });
            $this->assertTrue($db->inTransaction(), 'the inner commit must not close the outer scope');
        });

        $this->assertFalse($db->inTransaction());
        $this->assertSame(1, $this->latestCount(self::TAG), 'the nested insert was kept');
    }

    /** An inner failure must take the whole thing down, not just its own scope. */
    public function testAnInnerFailureRollsBackTheOuterWork(): void
    {
        $db = self::db();

        try {
            $db->transactional(function () use ($db): void {
                $this->insertRow(self::TAG);
                $db->transactional(function (): void {
                    throw new \RuntimeException('inner failure');
                });
            });
        } catch (\RuntimeException $expected) {
            $this->assertSame('inner failure', $expected->getMessage());
        }

        $this->assertSame(0, $this->latestCount(self::TAG), 'the outer insert must be gone too');
        $this->assertFalse($db->inTransaction(), 'and no scope may be left open');
    }

    /** The nastiest case: an inner scope fails and something *swallows* its exception. */
    public function testASwallowedInnerFailurePreventsTheOuterCommit(): void
    {
        $db = self::db();

        try {
            $db->transactional(function () use ($db): void {
                $this->insertRow(self::TAG);
                try {
                    $db->transactional(function (): void {
                        throw new \RuntimeException('inner failure nobody reported');
                    });
                } catch (\RuntimeException) {
                }
            });
            $this->fail('the outer commit must refuse rather than keep half-failed work');
        } catch (\Throwable $refused) {
            $this->assertStringContainsString('rolled back', $refused->getMessage());
        }

        $this->assertSame(0, $this->latestCount(self::TAG));
        $this->assertFalse($db->inTransaction());
    }

    /** Rolling back with nothing open is a no-op, so unwinding code cannot fail on it. */
    public function testRollingBackWithNoScopeOpenIsHarmless(): void
    {
        $db = self::db();
        $db->rollBack();
        $this->assertFalse($db->inTransaction());
        $this->addToAssertionCount(1);
    }

    /** The real ACL write path, interrupted. */
    public function testAnInterruptedAclWriteLeavesThePageVisible(): void
    {
        $wiki = self::getWiki();
        $db = self::db();
        $pageManager = $wiki->services->get(PageManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $pageManager->save(self::TAG, [PageBody::CONTENT => 'content'], '', true);
        $aclService->save(self::TAG, 'read', '*');
        $this->assertSame(1, $this->latestCount(self::TAG));

        try {
            $db->transactional(function () use ($db): void {
                $db->query("UPDATE {$db->prefixTable('pages')} SET latest = 'N' WHERE tag = ?", [self::TAG]);

                throw new \RuntimeException('interrupted mid-ACL-write');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame(1, $this->latestCount(self::TAG));
        $pageManager->forget(self::TAG);
        $this->assertNotNull($pageManager->getOne(self::TAG), 'and the page is still readable');
    }
}
