<?php

namespace YesWiki\Test\Core\Controller;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Api\PageApiController;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for ticket 12 (templates absorbed into core): POST /api/pages/{tag}/metadatas replaces tools/templates's old loadmetadatas.php (confirmed dead, dropped) and savemetadatas.php (a bare $_POST AJAX handler) with a real API route.
 */
#[CoversMethod(PageApiController::class, 'savePageMetadatas')]
class ApiControllerMetadatasTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'ApiControllerMetadatasRegressionPage';

    /** The fixtures go when the tests do. */
    public static function tearDownAfterClass(): void
    {
        $pageManager = self::getWiki()->services->get(PageManager::class);
        foreach ([self::PAGE_TAG] as $tag) {
            $pageManager->deleteOrphaned($tag);
        }
    }

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(PageApiController::class));

        $pageManager = $wiki->services->get(PageManager::class);
        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'content'], '', true);

        return $wiki;
    }

    #[Depends('testWikiExisting')]
    public function testSavePageMetadatasPersistsAndMergesWithPrevious(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(PageApiController::class);
        $pageManager = $wiki->services->get(PageManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'need an existing admin user to exercise the write path');

        try {
            $authenticationService->login($admin);

            $request = Request::create('/api/pages/' . self::PAGE_TAG . '/metadatas', 'POST', [
                'metadatas' => ['theme' => 'margot', 'squelette' => '1col.tpl.html'],
            ]);
            $response = $controller->savePageMetadatas($request, self::PAGE_TAG);
            $data = json_decode((string)$response->getContent(), true);

            $this->assertSame('margot', $data['theme']);
            $this->assertSame('1col.tpl.html', $data['squelette']);
            $savedMetadata = $pageManager->getMetadata(self::PAGE_TAG);
            $this->assertNotNull($savedMetadata);
            $this->assertSame('margot', $savedMetadata['theme']);

            $request2 = Request::create('/api/pages/' . self::PAGE_TAG . '/metadatas', 'POST', [
                'metadatas' => ['style' => 'light.css'],
            ]);
            $controller->savePageMetadatas($request2, self::PAGE_TAG);
            $merged = $pageManager->getMetadata(self::PAGE_TAG);
            $this->assertNotNull($merged);

            $this->assertSame('margot', $merged['theme'], 'previous metadata must survive a partial update');
            $this->assertSame('light.css', $merged['style']);
        } finally {
            $authenticationService->logout();
        }
    }

    #[Depends('testWikiExisting')]
    public function testSavePageMetadatasRejectsEmptyMetadatas(YesWikiRuntime $wiki)
    {
        $controller = $wiki->services->get(PageApiController::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertInstanceOf(\YesWiki\Identity\Entity\User::class, $admin, 'need an existing admin user to exercise the write path');

        try {
            $authenticationService->login($admin);

            $request = Request::create('/api/pages/' . self::PAGE_TAG . '/metadatas', 'POST', []);
            $response = $controller->savePageMetadatas($request, self::PAGE_TAG);

            $this->assertSame(400, $response->getStatusCode());
        } finally {
            $authenticationService->logout();
        }
    }
}
