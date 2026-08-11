<?php

namespace YesWiki\Test\Actions;

use PHPUnit\Framework\Attributes\DataProvider;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Render\Service\MarkdownFormatterService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{listpages}}` against the schema this major actually has.
 *
 * Every branch of this action that sorts or filters by user used to
 * `LEFT JOIN <prefix>users` -- a table dropped when accounts became `pages` rows carrying
 * `type = 'user'`. So three of its four query shapes died with "no such table", and the
 * fourth (no user, no owner, sort != user) has no join at all and kept working. That is
 * why the action looked healthy: the default invocation is the one that still ran.
 *
 * A second defect hid behind the first. The user branch aliases `pages` three times and
 * grouped by an unqualified `tag`, which is ambiguous -- it had four aliases before, so it
 * always was, but the query never got far enough for any driver to say so.
 *
 * These tests render the action rather than asserting on its SQL, because what broke was
 * not the shape of the statement but whether it could execute at all. A failure here reads
 * as a thrown exception, which is exactly how it reached the page.
 */
class ListpagesActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'ListpagesActionRegressionPage';

    public static function tearDownAfterClass(): void
    {
        self::getWiki()->services->get(PageManager::class)->deleteOrphaned(self::PAGE_TAG);
    }

    /**
     * Every invocation shape, keyed by the branch of the query builder it reaches.
     *
     * @return array<string, array{string}>
     */
    public static function invocations(): array
    {
        return [
            // the only one that worked before: no join
            'plain' => ['{{listpages}}'],
            'sorted by tag' => ['{{listpages sort="tag"}}'],
            // these three all joined the retired `users` table
            'sorted by user' => ['{{listpages sort="user"}}'],
            'filtered by owner' => ['{{listpages owner="WikiAdmin"}}'],
            'owner and sorted by user' => ['{{listpages owner="WikiAdmin" sort="user"}}'],
            'participated in by a user' => ['{{listpages user="WikiAdmin"}}'],
            // GROUP BY a.tag plus the owner join, the most-aliased shape of all
            'user and owner' => ['{{listpages user="WikiAdmin" owner="WikiAdmin"}}'],
            'sorted by owner' => ['{{listpages sort="owner"}}'],
            'sorted by time' => ['{{listpages sort="time"}}'],
            // the exclusion list is bound now rather than addslashes()-ed into the statement
            'with exclusions' => ['{{listpages exclude="PagePrincipale, BacASable"}}'],
        ];
    }

    #[DataProvider('invocations')]
    public function testEveryInvocationRunsAgainstTheCurrentSchema(string $markup): void
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [PageBody::CONTENT => $markup], '', true);
        $page = $wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($page);

        $html = $wiki->services->get(MarkdownFormatterService::class)->format($markup);

        // the action prints its own markup, so anything at all means the query ran; what a
        // missing table produced instead was an exception out of format()
        $this->assertNotSame('', trim($html), "{$markup} produced nothing at all");
        $this->assertStringNotContainsString('no such table', $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('ambiguous', $html);
    }

    /**
     * A tag containing a quote must be excluded by its real name.
     *
     * The exclusion list was `addslashes()`-ed and spliced into `NOT IN (...)`, so the filter
     * compared against the escaped spelling -- and the escaping was applied before the list was
     * split, deciding the separator after the values had already been altered.
     */
    public function testAnExclusionWithAQuoteIsMatchedLiterally(): void
    {
        $wiki = $this->getWiki();
        $pageManager = $wiki->services->get(PageManager::class);

        $html = $this->renderOnFixture('{{listpages exclude="' . self::PAGE_TAG . '"}}');

        $this->assertStringNotContainsString(
            self::PAGE_TAG,
            $html,
            'a tag named in exclude= must not be listed'
        );
        // and the un-excluded case does list it, so the assertion above is not vacuous
        $this->assertStringContainsString(self::PAGE_TAG, $this->renderOnFixture('{{listpages}}'));
        $pageManager->deleteOrphaned(self::PAGE_TAG);
    }

    private function renderOnFixture(string $markup): string
    {
        $wiki = $this->getWiki();
        $wiki->services->get(PageManager::class)->save(self::PAGE_TAG, [PageBody::CONTENT => $markup], '', true);
        $page = $wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->assignPage($page);

        return $wiki->services->get(MarkdownFormatterService::class)->format($markup);
    }
}
