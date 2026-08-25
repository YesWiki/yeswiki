<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\YesWikiRuntime;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 11's modernization of handlers/page/edit.php's save path (raw $_POST/$_REQUEST -> Symfony Request).
 */
class EditHandlerSaveTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'EditHandlerSaveRegressionPage';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(YesWikiRuntime::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testDisplaySavePreviewAndConflictDetection(YesWikiRuntime $wiki): void
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);

        $pageManager->save(self::PAGE_TAG, [PageBody::CONTENT => 'original content'], '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);
        $this->assertNotNull($page);

        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->services->get(\YesWiki\Identity\Service\AclService::class)->isAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $authenticationService->login($admin);

        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setTag(self::PAGE_TAG);
        $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($page);
        $wiki->services->get(PageManager::class)->getOne(self::PAGE_TAG);

        $GLOBALS['yeswikiServices'] = $wiki->services;

        try {
            $_POST = [];
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
            $output = $wiki->services->get(\YesWiki\Render\Service\Performer::class)->run('edit', 'handler', []);
            $this->assertStringContainsString('aceditor-container', $output, 'the edit form must render');

            $_POST = ['submit' => 'preview', 'body' => 'preview body **bold**', 'previous' => $page['id']];
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
            $output = $wiki->services->get(\YesWiki\Render\Service\Performer::class)->run('edit', 'handler', []);
            $this->assertStringContainsString('<strong>bold</strong>', $output, 'preview must format the submitted body');

            $_POST = ['submit' => 'Sauver', 'body' => 'NEW SAVED CONTENT', 'previous' => $page['id']];
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
            $redirected = false;
            try {
                $wiki->services->get(\YesWiki\Render\Service\Performer::class)->run('edit', 'handler', []);
            } catch (ExitException $e) {
                $redirected = true;
            }
            $this->assertTrue($redirected, 'a successful save must redirect');
            $reloaded = $pageManager->getOne(self::PAGE_TAG);
            $this->assertNotNull($reloaded);
            $this->assertSame('NEW SAVED CONTENT', trim(PageBody::content($reloaded['body'])));

            $_POST = ['submit' => 'Sauver', 'body' => 'CONFLICTING CONTENT', 'previous' => $page['id']];
            $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->replace(Request::createFromGlobals());
            $wiki->services->get(\YesWiki\Kernel\Service\PageContext::class)->setPage($reloaded);
            $wiki->services->get(\YesWiki\Render\Service\Performer::class)->run('edit', 'handler', []);
            $stillSaved = $pageManager->getOne(self::PAGE_TAG);
            $this->assertNotNull($stillSaved);
            $this->assertSame('NEW SAVED CONTENT', trim(PageBody::content($stillSaved['body'])), 'a stale save must be rejected, not silently overwrite');
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            $authenticationService->logout();
            unset($GLOBALS['wiki']);
        }
    }
}
