<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Render\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** `{{trail}}` against a summary page that lists nothing. */
class TrailActionTest extends YesWikiTestCase
{
    private const SUMMARY = 'TrailActionSummaryPage';
    private const MEMBER = 'TrailActionMemberPage';

    private function summaryContaining(string $body): void
    {
        $wiki = self::getWiki();
        $wiki->services->get(PageManager::class)->save(self::SUMMARY, [PageBody::CONTENT => $body], '', true);
        $wiki->services->get(PageManager::class)->save(self::MEMBER, [PageBody::CONTENT => 'a member'], '', true);
        $wiki->services->get(PageContext::class)->assignPage(
            $wiki->services->get(PageManager::class)->getOne(self::MEMBER)
        );
    }

    /** The fixtures go when the tests do. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::SUMMARY, self::MEMBER] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testASummaryThatListsNothingRendersNoTrailRatherThanDying(): void
    {
        $this->summaryContaining('Just a sentence, with no indented list at all.');

        $html = self::getWiki()->services->get(Performer::class)
            ->run('trail', 'action', ['toc' => self::SUMMARY]);

        $this->assertSame('', $html, 'an empty summary should render nothing at all');
    }

    public function testASummaryWithAListStillDrawsTheTrail(): void
    {
        $this->summaryContaining("Pages:\n  - " . self::MEMBER . "\n  - PagePrincipale\n");

        $html = self::getWiki()->services->get(Performer::class)
            ->run('trail', 'action', ['toc' => self::SUMMARY]);

        $this->assertStringContainsString('pager', $html, 'the trail should render for a real summary');
    }
}
