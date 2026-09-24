<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Retired core actions leave the page; everything else, extension calls included, stays exactly where it was. */
class RetiredCoreActionsAreRemovedTest extends YesWikiTestCase
{
    private const TAG = 'TestRetiredCoreActionsAreRemoved';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20260924130000_RetiredCoreActionsAreRemoved.php';
        require_once 'src/migrations/20260802130000_RewriteRetiredSearchActions.php';
    }

    public function testRetiredCallsGoAndTheRestIsUntouched(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $before = "# Mes contenus\n\n{{myfavorites}}\n\nUn texte {{orphanedpages}} au milieu.\n\n"
            . "{{learnerdashboard}}\n{{button link=\"Page\" text=\"Aller\"}}\n"
            . "{{diaporama page=\"X\"}}\ncontenu\n{{end elem=\"diaporama\"}}";

        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::TAG, '2020-01-01 00:00:00', PageBody::encode(['content' => $before]), '', '', 'Y', 'page', '']
        );

        try {
            [$removed, $tags] = (new \RetiredCoreActionsAreRemoved())->remove($dbService, \RetiredCoreActionsAreRemoved::RETIRED, self::TAG);

            $this->assertSame(['myfavorites' => 1, 'orphanedpages' => 1, 'diaporama' => 2], $removed);
            $this->assertSame([self::TAG], $tags);
            $row = $dbService->loadSingle("SELECT body FROM {$pages} WHERE tag = ?", [self::TAG]);
            $this->assertSame(
                "# Mes contenus\n\n\nUn texte  au milieu.\n\n{{learnerdashboard}}\n{{button link=\"Page\" text=\"Aller\"}}\ncontenu\n",
                PageBody::content(PageBody::decode((string)$row['body'])),
                'an extension action such as learnerdashboard is not the core\'s to remove'
            );
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }

    /** Doryphore's moteurrecherche must become the search button before a later rename turns it into a retired searchform. */
    public function testTheDoryphoreSearchCallBecomesTheSearchButton(): void
    {
        $migration = new \RewriteRetiredSearchActions();
        $rewrite = (new \ReflectionClass($migration))->getMethod('rewriteBody');

        $this->assertSame(
            ['content' => '{{button icon="loupe" link="search"}}'],
            $rewrite->invoke($migration, ['content' => '{{moteurrecherche template="moteurrecherche_button.tpl.html"}}'])
        );
    }
}
