<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\Depends;
use Symfony\Component\HttpFoundation\Request;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 11's modernization of handlers/page/edit.php's save
 * path (raw $_POST/$_REQUEST -> Symfony Request). A first pass called
 * $this->getRequest() (a YesWikiPerformable helper) from this bare-script handler,
 * where $this is the Wiki instance itself -- Wiki has no such method, only a public
 * $request property -- which silently broke the whole save flow (caught here, not
 * in production, via this test).
 */
class EditHandlerSaveTest extends YesWikiTestCase
{
    private const PAGE_TAG = 'EditHandlerSaveRegressionPage';

    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki->services->get(Wiki::class);
    }

    #[Depends('testWikiExisting')]
    public function testDisplaySavePreviewAndConflictDetection(Wiki $wiki)
    {
        $pageManager = $wiki->services->get(PageManager::class);
        $authenticationService = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);

        $pageManager->save(self::PAGE_TAG, 'original content', '', true);
        $page = $pageManager->getOne(self::PAGE_TAG);

        $admin = current(array_filter($userManager->getAll(), fn ($u) => $wiki->UserIsAdmin($u['name'])));
        $this->assertNotFalse($admin, 'need an existing admin user to exercise write access');
        $authenticationService->login($admin);

        $wiki->tag = self::PAGE_TAG;
        $wiki->page = $page;
        $wiki->LoadPage(self::PAGE_TAG);

        // the edit form renders {{aceditor}}, whose ActionsBuilderService::getData()
        // calls bazar's baz_forms_and_lists_ids(), which reads $GLOBALS['wiki'] --
        // normally populated by the production HTTP bootstrap, not the test harness
        // (same workaround as FiltertagsActionTest)
        $GLOBALS['wiki'] = $wiki;

        try {
            // display form (no submit)
            $_POST = [];
            $wiki->request = Request::createFromGlobals();
            $output = $wiki->Method('edit');
            $this->assertStringContainsString('aceditor-container', $output, 'the edit form must render');

            // preview
            $_POST = ['submit' => 'preview', 'body' => 'preview body **bold**', 'previous' => $page['id']];
            $wiki->request = Request::createFromGlobals();
            $output = $wiki->Method('edit');
            $this->assertStringContainsString('<strong>bold</strong>', $output, 'preview must format the submitted body');

            // real save
            $_POST = ['submit' => 'Sauver', 'body' => 'NEW SAVED CONTENT', 'previous' => $page['id']];
            $wiki->request = Request::createFromGlobals();
            $redirected = false;
            try {
                $wiki->Method('edit');
            } catch (ExitException $e) {
                $redirected = true;
            }
            $this->assertTrue($redirected, 'a successful save must redirect');
            $reloaded = $pageManager->getOne(self::PAGE_TAG);
            $this->assertSame('NEW SAVED CONTENT', trim($reloaded['body']));

            // stale-edit conflict: submitting against the OLD 'previous' id again must not overwrite
            $_POST = ['submit' => 'Sauver', 'body' => 'CONFLICTING CONTENT', 'previous' => $page['id']];
            $wiki->request = Request::createFromGlobals();
            $wiki->page = $reloaded;
            $wiki->Method('edit');
            $stillSaved = $pageManager->getOne(self::PAGE_TAG);
            $this->assertSame('NEW SAVED CONTENT', trim($stillSaved['body']), 'a stale save must be rejected, not silently overwrite');
        } finally {
            $pageManager->deleteOrphaned(self::PAGE_TAG);
            $authenticationService->logout();
            unset($GLOBALS['wiki']);
        }
    }
}
