<?php

namespace YesWiki\Test\Actions;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Render\Service\ActionRunner;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\TagsManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `{{admintag}}` deletes keyword index rows only on a POST carrying a valid token (GHSA-5c27-6gcm-hc7x).
 *
 * Every case here is a refusal or a markup assertion, which is what the fix is about: a valid token cannot be presented from the command line at all, since the checker reads the real request through filter_input() rather than the $_POST array.
 */
class AdmintagActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'AdmintagTestPage';
    private const TAG_VALUE = 'AdmintagTestTagValue';

    private \YesWiki\Core\YesWikiRuntime $wiki;
    private PageManager $pageManager;
    private ?Request $previousRequest = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->previousRequest = $this->wiki->services->get(CurrentRequest::class)->get();

        $this->pageManager->save(self::PAGE_TAG, [
            PageBody::CONTENT => 'tagged content',
            PageBody::KEYWORDS => [self::TAG_VALUE],
        ], '', true);
        $this->wiki->services->get(SearchIndexer::class)->drain(1000);

        if (empty($this->wiki->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
    }

    protected function tearDown(): void
    {
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(SearchIndexer::class)->delete(self::PAGE_TAG);
        $this->wiki->services->get(AuthenticationService::class)->logout();
        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        parent::tearDown();
    }

    /** The screen addresses pairs, not surrogate ids, since ticket 62. */
    private function postedPair(): string
    {
        return self::TAG_VALUE . \YesWiki\Content\Action\AdminTagAction::PAIR_SEPARATOR . self::PAGE_TAG;
    }

    /** Whether the fixture's keyword is still on the page -- the body is the truth it would be deleted from. */
    private function keywordIsStillThere(): bool
    {
        return in_array(
            self::TAG_VALUE,
            TagsManager::keywordsOf($this->pageManager->getOne(self::PAGE_TAG, null, false, true)),
            true
        );
    }

    /**
     * @param array<string, string> $post
     * @param array<string, string> $query
     */
    private function runAdmintag(string $method = 'GET', array $post = [], array $query = []): string
    {
        $this->wiki->services->get(CurrentRequest::class)->replace(
            Request::create('/', $method, $method === 'POST' ? $post : $query)
        );

        return (string)$this->wiki->services->get(ActionRunner::class)->action('admintag', []);
    }

    public function testTheKeywordIsThereToBeginWith(): void
    {
        $this->assertTrue($this->keywordIsStillThere(), 'the fixture keyword was not saved');
        $this->assertContains(
            ['keyword' => self::TAG_VALUE, 'tag' => self::PAGE_TAG],
            $this->wiki->services->get(TagsManager::class)->pairs(),
            'the fixture keyword was not indexed'
        );
    }

    public function testAGetRequestDeletesNothing(): void
    {
        $this->runAdmintag('GET', [], ['delete_tag' => $this->postedPair()]);

        $this->assertTrue($this->keywordIsStillThere());
    }

    public function testAPostWithoutATokenDeletesNothing(): void
    {
        $this->runAdmintag('POST', ['delete_tag' => $this->postedPair()]);

        $this->assertTrue($this->keywordIsStillThere());
    }

    public function testAPostWithAnInvalidTokenDeletesNothing(): void
    {
        $this->runAdmintag('POST', ['delete_tag' => $this->postedPair(), 'csrf-token' => 'not-a-token']);

        $this->assertTrue($this->keywordIsStillThere());
    }

    public function testANonAdminPostDeletesNothing(): void
    {
        $this->wiki->services->get(AuthenticationService::class)->logout();

        $this->runAdmintag('POST', ['delete_tag' => $this->postedPair(), 'csrf-token' => 'not-a-token']);

        $this->assertTrue($this->keywordIsStillThere());
    }

    /** The bulk button posts every pair of one keyword, and removing them edits each body. */
    public function testTheBulkButtonRemovesTheKeywordFromEveryPage(): void
    {
        $second = self::PAGE_TAG . 'Second';
        $this->pageManager->save($second, [
            PageBody::CONTENT => 'also tagged',
            PageBody::KEYWORDS => [self::TAG_VALUE],
        ], '', true);
        $this->wiki->services->get(SearchIndexer::class)->drain(1000);

        try {
            $tagsManager = $this->wiki->services->get(TagsManager::class);
            $pairs = $tagsManager->pairs([self::TAG_VALUE]);
            $this->assertCount(2, $pairs, 'both fixture pages carry the keyword');

            $html = $this->runAdmintag();
            $this->assertStringContainsString('delete_all_tags', $html, 'the bulk button is only drawn for a shared keyword');

            $tagsManager->remove($pairs);
            foreach ([self::PAGE_TAG, $second] as $tag) {
                $this->wiki->services->get(SearchIndexer::class)->index($tag);
                $this->assertSame(
                    [],
                    TagsManager::keywordsOf($this->pageManager->getOne($tag, null, false, true)),
                    "removing in bulk must edit {$tag}'s body"
                );
            }
            $this->assertSame([], $tagsManager->pairs([self::TAG_VALUE]));
        } finally {
            $this->pageManager->deleteOrphaned($second);
            $this->wiki->services->get(SearchIndexer::class)->delete($second);
        }
    }

    public function testTheDeleteButtonIsAPostFormCarryingAToken(): void
    {
        $html = $this->runAdmintag();

        $this->assertStringContainsString(self::PAGE_TAG, $html);
        $this->assertStringNotContainsString('delete_tag=', $html, 'a GET link would delete on being followed');
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $html,
            'the admintag delete button is not a POST form carrying a csrf token'
        );
    }
}
