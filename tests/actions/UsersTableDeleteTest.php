<?php

namespace YesWiki\Test\Actions;

use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;
use YesWiki\Kernel\Service\Performer;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Deleting a user from the admin users table.
 *
 * It had never worked. The handler read
 *
 *     $userName = filter_var($post['username'], FILTER_UNSAFE_RAW);
 *     $userName = in_array($username, [false, null], true) ? '' : ...
 *
 * — `$username` with a lowercase n, and no such variable. It evaluated to null,
 * `in_array(null, [false, null], true)` is **true**, so the name was blanked on every request
 * and `getOneByName('')` found nobody: the admin was told "that user does not exist" whichever
 * user they picked.
 *
 * One `variable.undefined` entry in the PHPStan baseline had been saying so (ticket 40). The
 * suite never caught it because nothing drove this action.
 */
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
        // the action under test deletes a user, and doing so drops the current session, so the
        // cleanup below has to authenticate again before it may delete anything itself
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

        // The delete itself is refused here, correctly: this drives the action directly and
        // supplies no CSRF token. That is enough, because what broke was earlier than the
        // delete -- the name is echoed back in whatever the action reports, so a name that
        // survived says the posted value reached the lookup, and a blank one says it did not.
        //
        // Asserting on `_t('USERSTABLE_NOT_EXISTING_USER')` instead would have proved nothing:
        // that returns the template with `{username}` unreplaced, so it never matches the
        // rendered HTML either way, and the first version of this test passed with the bug in.
        // the message quotes the name -- `The user "{username}" was not deleted.` -- so the
        // quoted form is what distinguishes a surviving name from a blanked one. Asserting on
        // the bare name matched the users TABLE further down the same page, which lists every
        // account including this one, and passed with the bug in place.
        // ...and on the TEXT, not the markup: the table's own delete form carries
        // `value="UsersTableDeleteVictim"`, so the quoted name appears in an attribute too and
        // matching the raw HTML passed with the bug in place as well.
        $this->assertStringContainsString(
            '"' . self::VICTIM . '"',
            strip_tags($html),
            'the posted username was blanked before it reached the lookup'
        );
    }
}
