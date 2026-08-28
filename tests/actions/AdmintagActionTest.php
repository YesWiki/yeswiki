<?php

namespace YesWiki\Test\Actions;

use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Render\Service\ActionRunner;
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
    private TripleStore $tripleStore;
    private DbService $dbService;
    private ?string $tagId = null;
    private ?Request $previousRequest = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);
        $this->dbService = $this->wiki->services->get(DbService::class);
        $this->previousRequest = $this->wiki->services->get(CurrentRequest::class)->get();

        $this->pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'tagged content'], '', true);
        $this->tripleStore->create(self::PAGE_TAG, TagsManager::TAG_PROPERTY, self::TAG_VALUE, '', '');
        $this->tagId = $this->tagIdInDatabase();

        if (empty($this->wiki->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
    }

    protected function tearDown(): void
    {
        $this->tripleStore->delete(self::PAGE_TAG, TagsManager::TAG_PROPERTY, self::TAG_VALUE, '', '');
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(AuthenticationService::class)->logout();
        if ($this->previousRequest !== null) {
            $this->wiki->services->get(CurrentRequest::class)->replace($this->previousRequest);
        }
        parent::tearDown();
    }

    private function tagIdInDatabase(): ?string
    {
        $row = $this->dbService->loadSingle(
            'SELECT id FROM ' . $this->dbService->prefixTable('triples')
            . ' WHERE property = ? AND resource = ? AND value = ?',
            [TagsManager::TAG_PROPERTY, self::PAGE_TAG, self::TAG_VALUE]
        );

        return isset($row['id']) ? (string)$row['id'] : null;
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
        $this->assertNotNull($this->tagId, 'the fixture keyword was not indexed');
    }

    public function testAGetRequestDeletesNothing(): void
    {
        $this->runAdmintag('GET', [], ['delete_tag' => (string)$this->tagId]);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testAPostWithoutATokenDeletesNothing(): void
    {
        $this->runAdmintag('POST', ['delete_tag' => (string)$this->tagId]);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testAPostWithAnInvalidTokenDeletesNothing(): void
    {
        $this->runAdmintag('POST', ['delete_tag' => (string)$this->tagId, 'csrf-token' => 'not-a-token']);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testANonAdminPostDeletesNothing(): void
    {
        $this->wiki->services->get(AuthenticationService::class)->logout();

        $this->runAdmintag('POST', ['delete_tag' => (string)$this->tagId, 'csrf-token' => 'not-a-token']);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
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
