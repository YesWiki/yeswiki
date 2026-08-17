<?php

namespace YesWiki\Test\Actions;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Deleting a user from the admin users table. */
class UsersTableDeleteTest extends YesWikiTestCase
{
    private const VICTIM = 'UsersTableDeleteVictim';

    private function loginAsAdmin(): void
    {
        $wiki = self::getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $admin = current(array_filter(
            $wiki->services->get(UserManager::class)->getAll(),
            fn ($user) => $aclService->isAdmin($user['name'])
        ));
        $this->assertNotFalse($admin, 'need an existing admin on this wiki');
        $wiki->services->get(AuthenticationService::class)->login($admin);
    }

    protected function setUp(): void
    {
        $wiki = self::getWiki();
        $this->loginAsAdmin();

        $wiki->services->get(UserOperationsService::class)->create([
            'name' => self::VICTIM,
            'email' => 'victim@example.com',
            'password' => 'aLongEnoughPassword1!',
        ]);
    }

    protected function tearDown(): void
    {
        $wiki = self::getWiki();

        $this->loginAsAdmin();
        $user = $wiki->services->get(UserManager::class)->getOneByName(self::VICTIM);
        if (!empty($user)) {
            $wiki->services->get(UserOperationsService::class)->delete($user);
        }
        $wiki->services->get(AuthenticationService::class)->logout();
    }

    public function testTheNameFromThePostSurvivesAsFarAsTheLookup(): void
    {
        $wiki = self::getWiki();
        $this->assertNotEmpty(
            $wiki->services->get(UserManager::class)->getOneByName(self::VICTIM),
            'the fixture user was not created, so this test proves nothing'
        );

        $request = $wiki->services->get(\YesWiki\Kernel\Service\CurrentRequest::class)->get();
        $request->request->set('userstable_action', 'deleteUser');
        $request->request->set('username', self::VICTIM);

        $html = $wiki->services->get(Performer::class)->run('userstable', 'action', []);

        $this->assertStringContainsString(
            '"' . self::VICTIM . '"',
            strip_tags($html),
            'the posted username was blanked before it reached the lookup'
        );
    }
}
