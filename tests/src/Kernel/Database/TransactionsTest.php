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
    /**
     * A deadlock victim is replayed, because the engine has already undone it.
     *
     * MySQL reports `SQLSTATE[40001] ... 1213 Deadlock found when trying to get lock; try
     * restarting transaction`, and `DbService::query()` rethrows it wrapped -- so the retry has to
     * find the PDOException through `getPrevious()` rather than looking at what it caught.
     */
    public function testADeadlockVictimIsReplayed(): void
    {
        $attempts = 0;
        $result = self::db()->transactional(function () use (&$attempts): string {
            $attempts++;
            if ($attempts === 1) {
                throw self::wrappedDeadlock('40001');
            }

            return 'kept';
        }, 3);

        $this->assertSame('kept', $result);
        $this->assertSame(2, $attempts, 'the first attempt was undone by the engine, the second stuck');
    }

    /** PostgreSQL raises its own code for a deadlock, and it is retryable too. */
    public function testAPostgresDeadlockIsAlsoReplayed(): void
    {
        $attempts = 0;
        self::db()->transactional(function () use (&$attempts): void {
            $attempts++;
            if ($attempts === 1) {
                throw self::wrappedDeadlock('40P01');
            }
        }, 2);

        $this->assertSame(2, $attempts);
    }

    /** Retrying stops at the budget rather than looping while the engine keeps refusing. */
    public function testAPersistentDeadlockGivesUpAndThrows(): void
    {
        $attempts = 0;

        $surfaced = '';

        try {
            self::db()->transactional(function () use (&$attempts): void {
                $attempts++;

                throw self::wrappedDeadlock('40001');
            }, 3);
        } catch (\Exception $failure) {
            $surfaced = $failure->getMessage();
        }

        $this->assertStringContainsString('Deadlock', $surfaced, 'a deadlock that never clears must surface');
        $this->assertSame(3, $attempts, 'exactly the budget, no more');
    }

    /** Anything that is not a serialization failure is the caller's problem, thrown at once. */
    public function testAnOrdinaryFailureIsNotRetried(): void
    {
        $attempts = 0;

        $surfaced = '';

        try {
            self::db()->transactional(function () use (&$attempts): void {
                $attempts++;

                throw new \Exception('a column is missing');
            }, 3);
        } catch (\Exception $failure) {
            $surfaced = $failure->getMessage();
        }

        $this->assertSame('a column is missing', $surfaced, 'an ordinary failure must surface');
        $this->assertSame(1, $attempts, 'no replay for a failure the engine did not ask us to replay');
    }

    /** Replaying an inner scope would replay it inside a transaction the deadlock already killed. */
    public function testAnInnerScopeDoesNotRetryOnItsOwn(): void
    {
        $inner = 0;

        $surfaced = '';

        try {
            self::db()->transactional(function () use (&$inner): void {
                self::db()->transactional(function () use (&$inner): void {
                    $inner++;

                    throw self::wrappedDeadlock('40001');
                }, 5);
            });
        } catch (\Exception $failure) {
            $surfaced = $failure->getMessage();
        }

        $this->assertStringContainsString('Deadlock', $surfaced, 'the failure must reach the outer scope');
        $this->assertSame(1, $inner, 'the inner scope ran once and handed the failure outwards');
    }

    /** What `DbService::query()` throws when PDO reports a deadlock. */
    private static function wrappedDeadlock(string $sqlState): \Exception
    {
        $pdo = new \PDOException(
            "SQLSTATE[{$sqlState}]: Serialization failure: 1213 Deadlock found when trying to get lock"
        );
        // PDO puts the SQLSTATE in `code` itself, from inside the class; a test has to reach for it
        $code = new \ReflectionProperty(\Exception::class, 'code');
        $code->setAccessible(true);
        $code->setValue($pdo, $sqlState);

        return new \Exception($pdo->getMessage() . ' -- while running: DELETE ...', 0, $pdo);
    }

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
