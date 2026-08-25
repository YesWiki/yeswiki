<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Ticket 33's sweep. */
class RenameActionsAndParametersInBodiesTest extends YesWikiTestCase
{
    private const TAG = 'TestTicket33ActionRenames';

    public static function setUpBeforeClass(): void
    {
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

            $this->assertStringContainsString('{{searchform ', $latestRevision);

            $this->assertStringContainsString('template="moteurrecherche_button.twig"', $latestRevision);

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

        $body = PageBody::encode([
            'content' => 'The bazarliste action was renamed. {{entrylist template="bazarliste.twig"}}',
        ]);
        $this->insertRevision($dbService, $pages, $body, 'Y');

        try {
            $this->runMigration($wiki, $dbService);

            $this->assertSame(
                [json_decode($body, true)],
                array_map(
                    static fn (string $stored) => json_decode($stored, true),
                    $this->bodiesFor($dbService, $pages)
                ),
                'prose and parameter values are not action calls; this row had nothing to rewrite'
            );
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    private function runMigration(\YesWiki\Core\YesWikiRuntime $wiki, DbService $dbService): void
    {
        $migration = new \RenameActionsAndParametersInBodies();
        $migration->setServices($wiki->services);
        $migration->setDbService($dbService);
        $migration->setParams($wiki->services->get(ParameterBagInterface::class));
        $migration->run();
    }

    private function insertRevision(DbService $dbService, string $pages, string $body, string $latest): void
    {
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

    /**
     * @param list<string> $bodies
     */
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
