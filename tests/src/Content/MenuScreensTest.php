<?php

namespace YesWiki\Test\Content;

use YesWiki\Content\Controller\ListScreensController;
use YesWiki\Content\Controller\MenuController;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** The screens ticket 64 gives menus and value lists, which had none anybody could reach. */
class MenuScreensTest extends YesWikiTestCase
{
    protected function tearDown(): void
    {
        $this->getWiki()->services->get(AuthenticationService::class)->logout();
        parent::tearDown();
    }

    private function asAdmin(): void
    {
        if (empty($this->getWiki()->services->get(AuthenticationService::class)->connectFirstAdmin())) {
            $this->markTestSkipped('no admin account in the test wiki');
        }
    }

    /** Appearance > Menus lists what the wiki has and offers the editor. */
    public function testTheMenusScreenRenders(): void
    {
        $this->asAdmin();

        $html = (string)$this->getWiki()->services->get(MenuController::class)->menus()->getContent();

        $this->assertStringContainsString('data-yw-menu-rows="entries"', $html, 'the shared editor is not on the screen');
        $this->assertStringContainsString('MenuNavigation', $html, 'the chrome menus are listed');
    }

    /** Administration > Value lists: the same lists, with the buttons. */
    public function testTheListsAdminScreenRenders(): void
    {
        $this->asAdmin();

        $html = (string)$this->getWiki()->services->get(ListScreensController::class)->adminLists()->getContent();

        $this->assertStringContainsString('existing-lists-table', $html);
    }

    /** And the public one, which is the same data with the controls gone. */
    public function testThePublicListsScreenRendersForAnybody(): void
    {
        $html = (string)$this->getWiki()->services->get(ListScreensController::class)->publicLists()->getContent();

        $this->assertStringContainsString('yw-dashboard', $html);
        $this->assertStringNotContainsString('data-yw-menu-rows', $html, 'a reader is offered no editor');
    }
}
