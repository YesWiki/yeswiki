<?php

namespace YesWiki\Test\Actions;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{trail}}` against a summary page that lists nothing.
 *
 * `$pages` and `$currentPageIndex` were only assigned inside the `if (preg_match_all(...))`
 * that parses the summary's bullet list, so a summary page without one left both undefined:
 * two "Undefined variable" warnings on every render, and a trail that drew nothing without
 * saying why. Two `variable.undefined` entries in the PHPStan baseline had been saying so
 * (ticket 40).
 *
 * What this file pins is the *behaviour* — nothing for an empty summary, a pager for a real
 * one. The undefined reads themselves are PHPStan's to guard now that their suppressions are
 * gone, which is the better division: a warning is not something a test can assert on without
 * turning warnings into failures everywhere.
 */
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

    /**
     * The fixtures go when the tests do.
     *
     * phpunit runs against a real wiki -- the developer's own -- so a fixture left behind is a
     * page in somebody's index, for ever.
     */
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
