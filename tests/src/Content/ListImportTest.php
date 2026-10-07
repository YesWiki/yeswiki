<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\ListController;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Lists imported from another wiki. */
class ListImportTest extends YesWikiTestCase
{
    private const REMOTE_ID = 'ListeImportTestDepartements';
    private const PLAIN_PAGE = 'ListeImportTestPlainPage';
    private const ORIGIN = 'https://other.example.org/?BazaR/json&demand=lists';
    private const NODES = [['id' => '01', 'label' => 'Ain', 'children' => []]];

    private ListManager $listManager;
    private PageManager $pageManager;

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        $this->listManager = $this->getWiki()->services->get(ListManager::class);
        $this->pageManager = $this->getWiki()->services->get(PageManager::class);
        $this->created = [self::REMOTE_ID, self::PLAIN_PAGE];
        $this->loginAsAdmin();
    }

    protected function tearDown(): void
    {
        $this->loginAsAdmin();
        foreach (array_unique($this->created) as $id) {
            $this->getWiki()->services->get(AclService::class)->delete($id);
            $this->pageManager->deleteOrphaned($id);
        }
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    private function loginAsAdmin(): void
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }

    public function testAnImportedListKeepsItsRemoteIdAndOrigin(): void
    {
        $id = $this->import(self::REMOTE_ID, 'Départements', self::ORIGIN);

        $this->assertSame(self::REMOTE_ID, $id);
        $this->assertTrue($this->listManager->isList(self::REMOTE_ID));
        $this->assertSame('Ain', $this->listManager->getOne(self::REMOTE_ID)['nodes'][0]['label'] ?? null);
        $this->assertSame(self::ORIGIN, $this->listManager->originOf(self::REMOTE_ID));
    }

    public function testImportingAnExistingListReplacesIt(): void
    {
        $this->listManager->create('Old title', [], self::REMOTE_ID);

        $id = $this->import(self::REMOTE_ID, 'New title');

        $this->assertSame(self::REMOTE_ID, $id);
        $this->assertSame('New title', $this->listManager->getOne(self::REMOTE_ID)['title'] ?? null);
    }

    public function testAPageThatIsNotAListIsNeverOverwritten(): void
    {
        $this->pageManager->save(self::PLAIN_PAGE, ['content' => 'plain content'], '', true);

        $id = $this->import(self::PLAIN_PAGE, 'Plain');

        $this->assertNotSame(self::PLAIN_PAGE, $id);
        $this->assertFalse($this->listManager->isList(self::PLAIN_PAGE));
        $this->assertTrue($this->listManager->isList((string)$id));
    }

    public function testAnIdThatIsNotAPageNameIsReplaced(): void
    {
        $id = $this->import('../Liste Import Test', 'Invalid id');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', (string)$id);
    }

    public function testAReservedNameIsReplaced(): void
    {
        $id = $this->import('admin', 'Reserved');

        $this->assertNotSame('admin', $id);
    }

    public function testAListTheVisitorCannotWriteIsNotReplaced(): void
    {
        $this->listManager->create('Protected title', [], self::REMOTE_ID);
        $this->getWiki()->services->get(AclService::class)->save(self::REMOTE_ID, 'write', '@admins');
        $this->getWiki()->services->get(AuthenticationService::class)->logout();

        $id = $this->import(self::REMOTE_ID, 'Hijacked title');

        $this->assertNull($id);
        $this->assertSame('Protected title', $this->listManager->getOne(self::REMOTE_ID)['title'] ?? null);
    }

    public function testTheImportFormPostsTheRemoteIdAsKey(): void
    {
        $request = $this->getWiki()->services->get(CurrentRequest::class)->get();
        $request->request->set('imported-list', [self::REMOTE_ID => json_encode(['title' => 'Départements', 'nodes' => self::NODES])]);
        $request->request->set('imported-origin', self::ORIGIN);
        try {
            ob_start();
            $this->getWiki()->services->get(ListController::class)->displayAll();
            ob_end_clean();
        } finally {
            $request->request->remove('imported-list');
            $request->request->remove('imported-origin');
        }

        $this->assertTrue($this->listManager->isList(self::REMOTE_ID));
        $this->assertSame(self::ORIGIN, $this->listManager->originOf(self::REMOTE_ID));
    }

    private function import(string $remoteId, string $title, ?string $origin = null): ?string
    {
        $id = $this->listManager->import($remoteId, $title, self::NODES, $origin);
        if ($id !== null) {
            $this->created[] = $id;
        }

        return $id;
    }
}
