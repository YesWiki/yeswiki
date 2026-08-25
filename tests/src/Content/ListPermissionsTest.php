<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Content\Controller\ListController;
use YesWiki\Content\Service\ListManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Who may do what with the lists, now that the screen is everyone's to open. */
class ListPermissionsTest extends YesWikiTestCase
{
    private const LIST_ID = 'ListPermissionsTestList';
    private const SECRET = 'ListPermissionsTestSecret';

    public function testWikiExisting(): YesWikiRuntime
    {
        $wiki = $this->getWiki();
        $GLOBALS['yeswikiServices'] = $wiki->services;
        $this->assertTrue($wiki->services->has(ListController::class));

        return $wiki->services->get(YesWikiRuntime::class);
    }

    #[Depends('testWikiExisting')]
    public function testAVisitorReadsTheListsAndChangesNothing(YesWikiRuntime $wiki): void
    {
        $lists = $wiki->services->get(ListManager::class);
        $controller = $wiki->services->get(ListController::class);
        $authentication = $wiki->services->get(AuthenticationService::class);
        $authentication->logout();

        $lists->create('Une liste', [['id' => 'alpha', 'label' => 'Alpha', 'children' => []]], self::LIST_ID);

        try {
            $this->assertNotEmpty($lists->getOne(self::LIST_ID), 'the premise: the list exists');

            $table = (string)$controller->displayAll();
            $this->assertStringContainsString(self::LIST_ID, $table, 'a visitor sees the lists');
            $this->assertStringContainsString('Alpha', $table, '...and what they say');

            $this->assertStringNotContainsString(_t('BAZ_ACTIONS'), $table);

            $refused = _t('BAZ_DROIT_INSUFFISANT');
            $this->assertStringContainsString($refused, (string)$controller->create());
            $this->assertStringContainsString($refused, (string)$controller->update(self::LIST_ID));
            $this->assertStringContainsString($refused, (string)$controller->delete(self::LIST_ID));
            $this->assertNotEmpty($lists->getOne(self::LIST_ID), 'and the list is still there');

            $this->assertStringNotContainsString('ListController.php', (string)$controller->delete(self::LIST_ID));
        } finally {
            $wiki->services->get(PageManager::class)->deleteOrphaned(self::LIST_ID);
        }
    }

    /** A list nobody may read is a list nobody is shown. */
    #[Depends('testWikiExisting')]
    public function testAListWithAReadAclIsHiddenFromWhoeverItExcludes(YesWikiRuntime $wiki): void
    {
        $lists = $wiki->services->get(ListManager::class);
        $controller = $wiki->services->get(ListController::class);
        $authentication = $wiki->services->get(AuthenticationService::class);
        $userManager = $wiki->services->get(UserManager::class);
        $authentication->logout();

        $lists->create('Une liste secrète', [['id' => 'alpha', 'label' => 'Alpha', 'children' => []]], self::SECRET);
        $wiki->services->get(AclService::class)->save(self::SECRET, 'read', '@admins');

        try {
            $this->assertStringNotContainsString(
                self::SECRET,
                (string)$controller->displayAll(),
                'a read ACL is a read ACL on the screen that lists them too'
            );

            $admin = current(array_filter(
                $userManager->getAll(),
                fn ($user) => $wiki->services->get(AclService::class)->isAdmin($user['name'])
            ));
            if ($admin === false) {
                $this->markTestSkipped('this wiki has no admin to check the other direction with');
            }
            $authentication->login($admin);
            $forAnAdmin = (string)$controller->displayAll();
            $this->assertStringContainsString(self::SECRET, $forAnAdmin);

            $this->assertStringContainsString(_t('BAZ_ACTIONS'), $forAnAdmin);
        } finally {
            $authentication->logout();
            $wiki->services->get(PageManager::class)->deleteOrphaned(self::SECRET);
        }
    }
}
