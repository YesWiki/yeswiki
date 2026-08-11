<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 33's sweep. The rewriting rules themselves are covered by ActionCallRewriterTest; what
 * is left to prove here is what the migration adds on top of them, and it is one thing above all:
 * **it rewrites every revision, not just the latest.**.
 *
 * That is easy to get wrong and invisible when you do. A sweep over `latest = 'Y'` leaves every
 * historical revision saying `{{bazarliste}}`, so the revisions handler renders dead actions, a
 * diff across the migration boundary blames the last author for the rename, and restoring an
 * older revision resurrects a call to an action that no longer exists.
 *
 * The fixture is inserted with raw SQL because that is the only way to produce the state this
 * migration exists for: PageManager would happily save these bodies, but going through it would
 * also index and cache them, and the point is a row as an unmigrated wiki holds it.
 */
class RenameActionsAndParametersInBodiesTest extends YesWikiTestCase
{
    private const TAG = 'TestTicket33ActionRenames';

    public static function setUpBeforeClass(): void
    {
        // YesWikiMigration is only autoloadable once getWiki() has registered
        // src/autoload.inc.php's fallback autoloader
        self::getWiki();
        require_once 'src/migrations/20260811120000_RenameActionsAndParametersInBodies.php';
    }

    public function testEveryRevisionIsRewrittenAndReRunningChangesNothing(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        $old = PageBody::encode(['content' => '{{bazarliste id="1" champ="bf_titre" nbcol="2"}}']);
        $latest = PageBody::encode([
            'content' => "{{titrepage}}\n{{bazarcarto zoommolette=\"1\"}}\n"
                . '{{moteurrecherche template="moteurrecherche_button.twig"}}',
        ]);

        $this->insertRevision($dbService, $pages, $old, 'N');
        $this->insertRevision($dbService, $pages, $latest, 'Y');

        try {
            $this->runMigration($wiki, $dbService);

            // asserted on the DECODED body: the stored column is JSON, so a quote in it is `\"`
            // and a raw-string assertion would be testing the encoding rather than the rewrite
            $bodies = $this->contentsFor($dbService, $pages);
            $this->assertCount(2, $bodies, 'fixture: both revisions should still be there');

            $oldRevision = $this->bodyContaining($bodies, 'entrylist');
            $this->assertStringContainsString('{{entrylist ', $oldRevision, 'the OLD revision must be rewritten too');
            $this->assertStringContainsString('field="bf_titre"', $oldRevision);
            $this->assertStringContainsString('columns="2"', $oldRevision);
            $this->assertStringNotContainsString('bazarliste', $oldRevision);

            $latestRevision = $this->bodyContaining($bodies, 'pagetitle');
            $this->assertStringContainsString('{{pagetitle}}', $latestRevision);
            $this->assertStringContainsString('{{entrymap scrollwheelzoom="1"}}', $latestRevision);
            // the parameters of a renamed action: the case the maps' own documented ordering
            // would have silently skipped
            $this->assertStringContainsString('{{searchform ', $latestRevision);
            // and the template filename it was called with, which is user data
            $this->assertStringContainsString('template="moteurrecherche_button.twig"', $latestRevision);

            // idempotent: a second pass must be a no-op, byte for byte
            $before = $this->bodiesFor($dbService, $pages);
            $this->runMigration($wiki, $dbService);
            $this->assertSame($before, $this->bodiesFor($dbService, $pages), 'a second run must change nothing');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    public function testAPageWithNothingToRewriteIsLeftExactlyAsItWas(): void
    {
        $wiki = $this->getWiki();
        $dbService = $wiki->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        // deliberately mentions a renamed action in prose and in a template value -- neither is
        // action position, so the row must not be touched at all
        $body = PageBody::encode([
            'content' => 'The bazarliste action was renamed. {{entrylist template="bazarliste.twig"}}',
        ]);
        $this->insertRevision($dbService, $pages, $body, 'Y');

        try {
            $this->runMigration($wiki, $dbService);

            $this->assertSame(
                [$body],
                $this->bodiesFor($dbService, $pages),
                'prose and parameter values are not action calls; this row had nothing to rewrite'
            );
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    private function runMigration(\YesWiki\YesWikiRuntime $wiki, DbService $dbService): void
    {
        $migration = new \RenameActionsAndParametersInBodies();
        $migration->setServices($wiki->services);
        $migration->setDbService($dbService);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    private function insertRevision(DbService $dbService, string $pages, string $body, string $latest): void
    {
        // `user` and `time` are reserved words on PostgreSQL and must be quoted, or this fixture
        // cannot be inserted on one of the three supported drivers
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, '2026-01-01 00:00:00', $body, '', '', $latest, 'page', '']
        );
    }

    /**
     * The raw stored column, for byte-for-byte comparisons.
     *
     * @return list<string>
     */
    private function bodiesFor(DbService $dbService, string $pages): array
    {
        $rows = $dbService->loadAll(
            "SELECT body FROM {$pages} WHERE tag = ? ORDER BY id",
            [self::TAG]
        );

        return array_map(static fn (array $row): string => (string)$row['body'], $rows);
    }

    /**
     * The decoded `content` of each revision, for assertions about wiki syntax.
     *
     * @return list<string>
     */
    private function contentsFor(DbService $dbService, string $pages): array
    {
        return array_map(
            static fn (string $body): string => (string)(PageBody::decode($body)['content'] ?? ''),
            $this->bodiesFor($dbService, $pages)
        );
    }

    /** @param list<string> $bodies */
    private function bodyContaining(array $bodies, string $needle): string
    {
        foreach ($bodies as $body) {
            if (str_contains($body, $needle)) {
                return $body;
            }
        }
        $this->fail("no revision contained '{$needle}'; got: " . implode(' | ', $bodies));
    }
}
