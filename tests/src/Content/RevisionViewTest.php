<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Content\Service\RevisionView;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The history of a page: revisions numbered and spread along a line, and each one shown rendered, as code or as a difference. */
class RevisionViewTest extends YesWikiTestCase
{
    private const PAGE = 'RevisionViewTestPage';

    protected function tearDown(): void
    {
        $this->getWiki()->services->get(PageManager::class)->deleteOrphaned(self::PAGE);
        parent::tearDown();
    }

    private function view(): RevisionView
    {
        return $this->getWiki()->services->get(RevisionView::class);
    }

    public function testTheNewestIsNumberedLastAndSitsAtTheEnd(): void
    {
        $line = $this->view()->timeline([
            ['id' => 3, 'time' => '2026-03-01 10:00:00', 'user' => 'c'],
            ['id' => 2, 'time' => '2026-02-01 10:00:00', 'user' => 'b'],
            ['id' => 1, 'time' => '2026-01-01 10:00:00', 'user' => 'a'],
        ], 7);

        $this->assertSame([7, 6, 5], array_column($line, 'number'), 'the numbers count from the total, not from what is listed');
        $this->assertEqualsWithDelta(100.0, $line[0]['place'], 0.001);
        $this->assertEqualsWithDelta(0.0, $line[2]['place'], 0.001);
        $this->assertSame('c', $line[0]['user']);
    }

    public function testRevisionsSavedTogetherArePushedApart(): void
    {
        $line = $this->view()->timeline([
            ['id' => 3, 'time' => '2026-03-01 10:00:02', 'user' => ''],
            ['id' => 2, 'time' => '2026-03-01 10:00:01', 'user' => ''],
            ['id' => 1, 'time' => '2026-01-01 10:00:00', 'user' => ''],
        ], 3);

        $this->assertGreaterThanOrEqual(1.299, $line[0]['place'] - $line[1]['place'], 'two saves of one day overlap');
        $this->assertGreaterThanOrEqual(0.0, $line[2]['place']);
    }

    public function testTheCodeOfAPageIsShownAsTextAndItsChangesAsMarks(): void
    {
        $pages = $this->getWiki()->services->get(PageManager::class);
        $pages->save(self::PAGE, [PageBody::CONTENT => 'Avant <b>gras</b>'], '', true, '2026-01-01 10:00:00');
        $pages->save(self::PAGE, [PageBody::CONTENT => 'Après <b>gras</b>'], '', true, '2026-01-02 10:00:00');
        $latest = $pages->getOne(self::PAGE);
        $this->assertNotNull($latest);

        $code = $this->view()->show($latest, 'current', true);
        $this->assertStringContainsString('&lt;b&gt;gras&lt;/b&gt;', $code, 'the code is markup to read, not to run');

        $changes = $this->view()->show($latest, 'commit_diff', true);
        $this->assertStringContainsString('<ins', $changes);
        $this->assertStringContainsString('<del', $changes);
    }
}
