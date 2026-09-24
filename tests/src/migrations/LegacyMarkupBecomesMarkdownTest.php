<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The wakka to Markdown sweep, run on its own rows only so the developer's wiki is never rewritten. */
class LegacyMarkupBecomesMarkdownTest extends YesWikiTestCase
{
    private const TAG = 'TestLegacyMarkupBecomesMarkdown';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260924120000_LegacyMarkupBecomesMarkdown.php';
    }

    public function testRevisionsBeforeTheUpgradeAreConvertedAndLaterOnesAreNot(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        $legacy = PageBody::encode(['content' => "======Titre======\nune //ligne//\nune autre"]);
        $markdown = PageBody::encode(['content' => "# Déjà du Markdown\n---\nun paragraphe\nsur deux lignes"]);

        $this->insertRevision($dbService, $pages, '2020-01-01 00:00:00', $legacy, 'N');
        $this->insertRevision($dbService, $pages, '2099-01-01 00:00:00', $markdown, 'Y');

        try {
            [$tags, $revisions] = (new \LegacyMarkupBecomesMarkdown())->rewrite($dbService, '2030-01-01 00:00:00', self::TAG);

            $this->assertSame([self::TAG], $tags);
            $this->assertSame(1, $revisions);
            $this->assertSame(
                [
                    ['time' => '2020-01-01 00:00:00', 'content' => "# Titre\n\nune *ligne*\\\nune autre"],
                    ['time' => '2099-01-01 00:00:00', 'content' => "# Déjà du Markdown\n---\nun paragraphe\nsur deux lignes"],
                ],
                $this->contents($dbService, $pages)
            );

            [, $again] = (new \LegacyMarkupBecomesMarkdown())->rewrite($dbService, '2030-01-01 00:00:00', self::TAG);
            $this->assertSame(0, $again, 'a second run finds nothing left to convert');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    private function insertRevision(DbService $dbService, string $pages, string $time, string $body, string $latest): void
    {
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, $time, $body, '', '', $latest, 'page', '']
        );
    }

    /** @return list<array{time: string, content: string}> */
    private function contents(DbService $dbService, string $pages): array
    {
        $rows = $dbService->loadAll(
            "SELECT {$dbService->quoteIdentifier('time')} AS t, body FROM {$pages} WHERE tag = ? ORDER BY id",
            [self::TAG]
        );

        return array_map(
            fn (array $row): array => ['time' => (string)$row['t'], 'content' => PageBody::content(PageBody::decode((string)$row['body']))],
            $rows
        );
    }
}
