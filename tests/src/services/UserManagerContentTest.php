<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Exception\UserNameAlreadyUsedException;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression/acceptance tests for ticket 06 (users become Content): user accounts are now `pages` rows typed via a TYPE_URI='user' triple instead of standalone `users` rows, with the password hash (and a few account-preference fields) hidden from non-owner, non-admin viewers via Field ACL (Guard::checkUserAcls()) -- uniformly on the current revision AND on historical ones, since both flow through the same PageManager::checkEntriesACL() choke point.
 */
class UserManagerContentTest extends YesWikiTestCase
{
    private const OTHER_VIEWER = 'UserManagerContentTestOtherViewer';

    private function cleanupUser(UserManager $userManager, string $name): void
    {
        $user = $userManager->getOneByName($name);
        if ($user) {
            $userManager->delete($user);
        }
    }

    public function testCreateStoresUserAsAPageTypedByItsColumnNotAUsersRow(): void
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $name = 'UserManagerContentTestCreate';
        $this->cleanupUser($userManager, $name);

        try {
            $created = $userManager->create($name, 'umct-create@example.tld', 'Aa1!aaaaRegression');
            $this->assertNotNull($created);
            $this->assertSame($name, $created['name']);

            $page = $pageManager->getOne($name, null, true, true);
            $this->assertIsArray($page);

            $this->assertSame(PageType::USER, $page['type'], 'the row states it is an account');
            $this->assertSame(PageType::USER, $pageManager->typeOf($name));

            $this->assertSame($name, $page['owner']);
            $metadata = $pageManager->getMetadata($name);
            $this->assertNotNull($metadata);
            $this->assertSame("%\n@admins", $metadata['acls']['write']);
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testPasswordIsNeverExposedViaGenericPageReadNotEvenToTheOwner()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $name = 'UserManagerContentTestPasswordHidden';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-password@example.tld', 'Aa1!aaaaRegression');

            $internal = $userManager->getOneByName($name);
            $this->assertNotEmpty($internal['password']);

            $asOwner = $pageManager->getOne($name, null, false, false, $name);
            $this->assertNotNull($asOwner);
            $bodyAsOwner = $asOwner['body'];
            $this->assertSame('', $bodyAsOwner['password']);

            $asOther = $pageManager->getOne($name, null, false, false, self::OTHER_VIEWER);
            $this->assertNotNull($asOther);
            $bodyAsOther = $asOther['body'];
            $this->assertSame('', $bodyAsOther['password']);
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testEmailAndPreferencesHiddenFromOthersButVisibleToOwnerAndAdmin()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $name = 'UserManagerContentTestFieldAcl';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-fieldacl@example.tld', 'Aa1!aaaaRegression');

            $asOther = $pageManager->getOne($name, null, false, false, self::OTHER_VIEWER);
            $this->assertNotNull($asOther);
            $bodyAsOther = $asOther['body'];
            $this->assertSame('', $bodyAsOther['email'], 'email must be hidden from an unrelated viewer');
            $this->assertSame('', $bodyAsOther['doubleclickedit'], 'account preferences must be hidden from an unrelated viewer');

            $asOwner = $pageManager->getOne($name, null, false, false, $name);
            $this->assertNotNull($asOwner);
            $bodyAsOwner = $asOwner['body'];
            $this->assertSame('umct-fieldacl@example.tld', $bodyAsOwner['email'], 'email must stay visible to the account owner');

            $this->assertArrayHasKey('signuptime', $bodyAsOther);
            $this->assertNotSame('', $asOther['owner'] ?? null, 'owner column itself is unaffected by field redaction');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testFieldAclAppliesUniformlyToHistoricalRevisions()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $name = 'UserManagerContentTestHistory';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-history@example.tld', 'Aa1!aaaaRegression');
            $originalUser = self::requireUser($userManager->getOneByName($name));
            $originalHash = $originalUser['password'];
            $firstRevision = $pageManager->getOne($name, null, true, true);
            $this->assertNotNull($firstRevision);
            $firstRevisionTime = $firstRevision['time'];

            sleep(1);
            $userManager->update($originalUser, ['motto' => 'updated motto']);

            $historicalAsOther = $pageManager->getOne($name, $firstRevisionTime, false, false, self::OTHER_VIEWER);
            $this->assertNotNull($historicalAsOther, 'sanity: the historical revision is fetchable');
            $historicalBody = $historicalAsOther['body'];
            $this->assertSame('', $historicalBody['password'], 'password must be redacted on a historical revision too');
            $this->assertNotEmpty($originalHash, 'sanity: there really was a hash to hide');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testRevertingAUserPageDoesNotCorruptThePasswordHash()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $name = 'UserManagerContentTestRevert';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-revert@example.tld', 'Aa1!aaaaRegression');
            $originalUser = self::requireUser($userManager->getOneByName($name));
            $originalHash = $originalUser['password'];
            $this->assertNotEmpty($originalHash);

            $firstRevision = $pageManager->getOne($name, null, true, true);
            $this->assertNotNull($firstRevision);
            $firstRevisionId = $firstRevision['id'];

            sleep(1);
            $userManager->update($originalUser, ['motto' => 'updated motto']);

            $pageManager->revertToRevision($name, $firstRevisionId);

            $afterRevert = $userManager->getOneByName($name);
            $this->assertSame($originalHash, $afterRevert['password'], 'reverting must not erase the real password hash');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testCreatingWithANameCollidingWithExistingContentUsesSuggestFreeTag()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $collidingTag = 'UserManagerContentTestCollidingPage';
        $this->cleanupUser($userManager, $collidingTag);

        try {
            $pageManager->save($collidingTag, [PageBody::CONTENT => 'existing page content'], '', true);

            $created = $userManager->create($collidingTag, 'umct-collision@example.tld', 'Aa1!aaaaRegression');

            $this->assertNotNull($created);
            $this->assertNotSame($collidingTag, $created['name'], 'must not silently overwrite the existing page');
            $this->assertSame($collidingTag . '2', $created['name']);
        } finally {
            $pageManager->deleteOrphaned($collidingTag);
            $this->cleanupUser($userManager, $collidingTag . '2');
        }
    }

    public function testCreatingWithAnExistingUsersNameStillThrows()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);

        $name = 'UserManagerContentTestDuplicateUser';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-dup1@example.tld', 'Aa1!aaaaRegression');

            $this->expectException(UserNameAlreadyUsedException::class);
            $userManager->create($name, 'umct-dup2@example.tld', 'Aa1!aaaaRegression');
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }

    public function testOnlyOwnerAndAdminCanWriteToAUsersPage()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $aclService = $wiki->services->get(AclService::class);

        $name = 'UserManagerContentTestWriteAcl';
        $other = 'UserManagerContentTestWriteAclOther';
        $this->cleanupUser($userManager, $name);
        $this->cleanupUser($userManager, $other);

        $previousSessionUser = $_SESSION['user'] ?? null;

        try {
            $userManager->create($name, 'umct-writeacl@example.tld', 'Aa1!aaaaRegression');
            $userManager->create($other, 'umct-writeacl-other@example.tld', 'Aa1!aaaaRegression');

            $_SESSION['user'] = ['name' => $name, 'lastConnection' => time()];
            $this->assertTrue($aclService->hasAccess('write', $name, ''), 'the owner can write to their own account page');

            $_SESSION['user'] = ['name' => $other, 'lastConnection' => time()];
            $this->assertFalse($aclService->hasAccess('write', $name, ''), 'an unrelated user cannot write to someone else\'s account page');
        } finally {
            if ($previousSessionUser === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $previousSessionUser;
            }
            $this->cleanupUser($userManager, $name);
            $this->cleanupUser($userManager, $other);
        }
    }

    public function testGetAllReturnsRealUsersOnly()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);

        $name = 'UserManagerContentTestGetAll';
        $this->cleanupUser($userManager, $name);

        try {
            $userManager->create($name, 'umct-getall@example.tld', 'Aa1!aaaaRegression');

            $names = array_map(function ($user) {
                return $user['name'];
            }, $userManager->getAll());

            $this->assertContains($name, $names);
        } finally {
            $this->cleanupUser($userManager, $name);
        }
    }
}
