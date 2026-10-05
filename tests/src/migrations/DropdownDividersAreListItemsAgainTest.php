<?php

namespace YesWiki\Test\Core\Migrations;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The divider repair, run on its own rows only so the developer's wiki is never rewritten. */
class DropdownDividersAreListItemsAgainTest extends YesWikiTestCase
{
    private const TAG = 'TestDropdownDividersAreListItemsAgain';

    private const BROKEN = "{{buttondropdown icon=\"cog\"}}\n- {{button text=\"a\" link=\"A\"}}\n-\n\n----\n\n- {{button text=\"b\" link=\"B\"}}\n{{end elem=\"buttondropdown\"}}";

    private const REPAIRED = "{{buttondropdown icon=\"cog\"}}\n- {{button text=\"a\" link=\"A\"}}\n- ***\n- {{button text=\"b\" link=\"B\"}}\n{{end elem=\"buttondropdown\"}}";

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261005121000_DropdownDividersAreListItemsAgain.php';
    }

    #[DataProvider('shapes')]
    public function testTheBrokenShapeAndOnlyItIsRepaired(string $content, string $expected): void
    {
        $this->assertSame($expected, \DropdownDividersAreListItemsAgain::repairContent($content));
        $this->assertSame($expected, \DropdownDividersAreListItemsAgain::repairContent($expected));
    }

    /** @return array<string, array{string, string}> */
    public static function shapes(): array
    {
        return [
            'the larobustesse.org dropdown' => [self::BROKEN, self::REPAIRED],
            'a nested divider takes back its level' => ["- un\n  - a\n  -\n\n----\n\n- deux\n- trois", "- un\n  - a\n  - ***\n  - deux\n- trois"],
            'two dividers in a row' => ["- a\n-\n\n----\n\n-\n\n----\n\n- b", "- a\n- ***\n- ***\n- b"],
            'trailing spaces' => ["- a\n- \n\n----  \n\n- b", "- a\n- ***\n- b"],
            'a rule between paragraphs' => ["texte\n\n----\n\nsuite", "texte\n\n----\n\nsuite"],
            'a rule after a list' => ["- a\n- b\n\n----\n\n- c", "- a\n- b\n\n----\n\n- c"],
            'a rule before prose' => ["- a\n-\n\n----\n\nsuite", "- a\n-\n\n----\n\nsuite"],
            'a lone dash after prose' => ["texte\n-\n\n----\n\n- b", "texte\n-\n\n----\n\n- b"],
        ];
    }

    public function testOnlyTheLatestRevisionIsRepairedAndOnce(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = $dbService->prefixTable('pages');

        $this->insertRevision($dbService, $pages, '2020-01-01 00:00:00', self::BROKEN, 'N');
        $this->insertRevision($dbService, $pages, '2021-01-01 00:00:00', self::BROKEN, 'Y');

        try {
            $migration = new \DropdownDividersAreListItemsAgain();

            $this->assertSame([self::TAG], $migration->repair($dbService, self::TAG));
            $this->assertSame([self::BROKEN, self::REPAIRED], $this->contents($dbService, $pages));
            $this->assertSame([], $migration->repair($dbService, self::TAG), 'a second run finds nothing left to repair');
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    private function insertRevision(DbService $dbService, string $pages, string $time, string $content, string $latest): void
    {
        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent)"
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [self::TAG, $time, PageBody::encode(['content' => $content]), '', '', $latest, 'page', '']
        );
    }

    /** @return list<string> */
    private function contents(DbService $dbService, string $pages): array
    {
        $rows = $dbService->loadAll("SELECT body FROM {$pages} WHERE tag = ? ORDER BY id", [self::TAG]);

        return array_map(fn (array $row): string => PageBody::content(PageBody::decode((string)$row['body'])), $rows);
    }
}
