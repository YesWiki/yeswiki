<?php

namespace YesWiki\Test\Core\Migrations;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Retiring `{{listpages}}`. */
class RetireListpagesTest extends YesWikiTestCase
{
    private const TAG = 'TestRetireListpages';

    public static function setUpBeforeClass(): void
    {
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

    private function runMigration(\YesWiki\Core\YesWikiRuntime $wiki, DbService $dbService): void
    {
        $migration = new \RetireListpages();
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
     * @return list<string>
     */
    private function bodiesFor(DbService $dbService, string $pages): array
    {
        $rows = $dbService->loadAll("SELECT body FROM {$pages} WHERE tag = ? ORDER BY id", [self::TAG]);

        return array_map(static fn (array $row): string => (string)$row['body'], $rows);
    }

    /**
     * @return list<string>
     */
    private function contentsFor(DbService $dbService, string $pages): array
    {
        return array_map(
            static fn (string $body): string => (string)(PageBody::decode($body)['content'] ?? ''),
            $this->bodiesFor($dbService, $pages)
        );
    }
}
