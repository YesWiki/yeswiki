<?php

namespace YesWiki\Test\Core\Migrations;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The calls to rss, trail and listpagestag leave the page, and the rest of it stays. */
class RssTrailAndListpagestagAreRemovedTest extends YesWikiTestCase
{
    private const TAG = 'TestRssTrailAndListpagestagAreRemoved';

    public static function setUpBeforeClass(): void
    {
        self::getWiki();
        require_once 'src/migrations/20261006110000_RssTrailAndListpagestagAreRemoved.php';
    }

    public function testTheirCallsGoAndTheRestStays(): void
    {
        $dbService = $this->getWiki()->services->get(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $before = "# Titre\n\n{{trail toc=\"Sommaire\"}}\n{{listpagestag tags=\"a\"}}\nTexte {{rss tags=\"a\"}} ici.\n{{tagcloud}}";

        $dbService->query(
            "INSERT INTO {$pages} (tag, {$dbService->quoteIdentifier('time')}, body, owner,"
            . " {$dbService->quoteIdentifier('user')}, latest, type, parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [self::TAG, '2020-01-01 00:00:00', PageBody::encode(['content' => $before]), '', '', 'Y', 'page', '']
        );

        try {
            [$removed, $tags] = (new \RetiredCoreActionsAreRemoved())->remove($dbService, \RssTrailAndListpagestagAreRemoved::RETIRED, self::TAG);

            $this->assertSame(['trail' => 1, 'listpagestag' => 1, 'rss' => 1], $removed);
            $this->assertSame([self::TAG], $tags);
            $row = $dbService->loadSingle("SELECT body FROM {$pages} WHERE tag = ?", [self::TAG]);
            $this->assertNotNull($row, 'fixture: the row must still be there');
            $this->assertSame("# Titre\n\nTexte  ici.\n{{tagcloud}}", PageBody::content(PageBody::decode((string)$row['body'])));
        } finally {
            $dbService->query("DELETE FROM {$pages} WHERE tag = ?", [self::TAG]);
        }
    }
}
