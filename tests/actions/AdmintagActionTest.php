<?php

namespace YesWiki\Test\Actions;

use Symfony\Component\Security\Csrf\CsrfTokenManager;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * {{admintag}} deletes tag triples only on a POST carrying a valid token.
 */
class AdmintagActionTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'AdmintagTestPage';
    private const TAG_VALUE = 'AdmintagTestTagValue';
    private const TAG_PROPERTY = 'http://outils-reseaux.org/_vocabulary/tag';

    private $wiki;
    private $pageManager;
    private $tripleStore;
    private $dbService;
    private $tagId;
    private $previousGet;
    private $previousPost;
    private $previousMethod;

    protected function setUp(): void
    {
        $this->wiki = $this->getWiki();
        $this->pageManager = $this->wiki->services->get(PageManager::class);
        $this->tripleStore = $this->wiki->services->get(TripleStore::class);
        $this->dbService = $this->wiki->services->get(DbService::class);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->previousGet = $_GET;
        $this->previousPost = $_POST;
        $this->previousMethod = $_SERVER['REQUEST_METHOD'] ?? null;

        $this->pageManager->save(self::PAGE_TAG, 'tagged content', '', true);
        $this->tripleStore->create(self::PAGE_TAG, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
        $this->tagId = $this->tagIdInDatabase();

        if (empty($this->wiki->services->get(AuthController::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }

        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $this->tripleStore->delete(self::PAGE_TAG, self::TAG_PROPERTY, self::TAG_VALUE, '', '');
        $this->pageManager->deleteOrphaned(self::PAGE_TAG);
        $this->wiki->services->get(AuthController::class)->logout();

        $_GET = $this->previousGet;
        $_POST = $this->previousPost;
        if ($this->previousMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->previousMethod;
        }
    }

    private function tagIdInDatabase(): ?string
    {
        $row = $this->dbService->loadSingle(
            'SELECT id FROM ' . $this->dbService->prefixTable('triples')
            . ' WHERE property = "' . self::TAG_PROPERTY . '"'
            . ' AND resource = "' . self::PAGE_TAG . '"'
            . ' AND value = "' . self::TAG_VALUE . '"'
        );

        return $row['id'] ?? null;
    }

    private function token(): string
    {
        return $this->wiki->services->get(CsrfTokenManager::class)->getToken('main')->getValue();
    }

    public function testAGetRequestDeletesNothing()
    {
        $_GET['delete_tag'] = $this->tagId;

        $this->wiki->Action('admintag', 1, []);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testAPostWithoutATokenDeletesNothing()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['delete_tag'] = $this->tagId;

        $this->wiki->Action('admintag', 1, []);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testAPostWithAnInvalidTokenDeletesNothing()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['delete_tag'] = $this->tagId;
        $_POST['csrf-token'] = 'not-a-token';

        $this->wiki->Action('admintag', 1, []);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testANonAdminPostDeletesNothing()
    {
        $this->wiki->services->get(AuthController::class)->logout();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['delete_tag'] = $this->tagId;
        $_POST['csrf-token'] = $this->token();

        $this->wiki->Action('admintag', 1, []);

        $this->assertSame($this->tagId, $this->tagIdInDatabase());
    }

    public function testTheDeleteButtonIsAPostFormCarryingAToken()
    {
        $html = $this->wiki->Action('admintag', 1, []);

        $this->assertStringContainsString(self::PAGE_TAG, $html);
        $this->assertStringNotContainsString('delete_tag=', $html);
        $this->assertMatchesRegularExpression(
            '/<form method="post"[^>]*>\s*<input type="hidden" name="csrf-token" value="[^"]+">/',
            $html,
            '`admintag` delete button is not a POST form carrying a csrf token'
        );
    }
}
