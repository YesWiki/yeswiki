<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Retiring `{{listpages}}`. The mapping itself is ListpagesRewriterTest's; what is left to
 * prove here is what the migration adds on top of it, and it is the same thing ticket 33's
 * sweep had to prove: **every revision, not just the latest.**.
 *
 * A sweep over `latest = 'Y'` would leave the history calling an action that no longer
 * exists -- so the revisions handler renders an error, a diff across the migration blames
 * the last author, and restoring an older revision brings the dead call back.
 *
 * The fixture is inserted with raw SQL because that is the only way to produce the state
 * this migration exists for: a row as an unmigrated wiki holds it, unindexed and uncached.
 */
class RetireListpagesTest extends YesWikiTestCase
{
    private const TAG = 'TestRetireListpages';

    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration is only autoloadable once getWiki() has registered
        // src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260813150000_RetireListpages.php';
    }

    public function testEveryRevisionIsRewrittenAndReRunningChangesNothing(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        $this->insertRevision(
            $dbService,
            $pages,
            PageBody::encode(['content' => '{{listpages sort="time"}}']),
            'N'
        );
        $this->insertRevision(
            $dbService,
            $pages,
            PageBody::encode(['content' => "Les pages :\n{{listpages template=\"card\"}}"]),
            'Y'
        );

        try {
            $this->runMigration($wiki, $dbService);

            $contents = $this->contentsFor($dbService, $pages);
            $this->assertCount(2, $contents, 'fixture: both revisions should still be there');
            foreach ($contents as $content) {
                $this->assertStringNotContainsString(
                    'listpages',
                    $content,
                    'a revision still calls an action that no longer exists'
                );
                $this->assertStringContainsString('{{entrylist id="', $content);
            }
            $this->assertStringContainsString('template="card"', $contents[1]);

            // idempotent: the second pass has nothing left to match
            $before = $this->bodiesFor($dbService, $pages);
            $this->runMigration($wiki, $dbService);
            $this->assertSame($before, $this->bodiesFor($dbService, $pages));
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    /** The Pages form is what the calls are rewritten ONTO, so the id must be that form's. */
    public function testTheListIsPointedAtThePagesForm(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');
        $form = $wiki->services->get(\YesWiki\Content\Service\FormManager::class)
            ->getByContentType(\YesWiki\Content\Entity\PageType::PAGE);
        $this->assertNotNull($form, 'this wiki has no Pages form to list the pages of');

        $this->insertRevision(
            $dbService,
            $pages,
            PageBody::encode(['content' => '{{listpages}}']),
            'Y'
        );

        try {
            $this->runMigration($wiki, $dbService);

            $this->assertSame(
                '{{entrylist id="' . $form['id'] . '"}}',
                $this->contentsFor($dbService, $pages)[0]
            );
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    private function runMigration(\YesWiki\YesWikiRuntime $wiki, DbService $dbService): void
    {
        $migration = new \RetireListpages();
        $migration->setServices($wiki->services);
        $migration->setDbService($dbService);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    private function insertRevision(DbService $dbService, string $pages, string $body, string $latest): void
    {
        // `user` and `time` are reserved words on PostgreSQL and must be quoted, or this
        // fixture cannot be inserted on one of the three supported drivers
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, '2026-01-01 00:00:00', $body, '', '', $latest, 'page', '']
        );
    }

    /** @return list<string> */
    private function bodiesFor(DbService $dbService, string $pages): array
    {
        $rows = $dbService->loadAll("SELECT body FROM {$pages} WHERE tag = ? ORDER BY id", [self::TAG]);

        return array_map(static fn (array $row): string => (string)$row['body'], $rows);
    }

    /** @return list<string> */
    private function contentsFor(DbService $dbService, string $pages): array
    {
        return array_map(
            static fn (string $body): string => (string)(PageBody::decode($body)['content'] ?? ''),
            $this->bodiesFor($dbService, $pages)
        );
    }
}
