<?php

namespace YesWiki\Test\Core\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Identity\Exception\BadFormatPasswordException;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Identity\Service\PasswordHasherFactory;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;
use YesWiki\Wiki;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression tests for ticket 08 (tools/login absorbed into core):
 * - the {{login}}/{{lostpassword}}/{{listusers}} tags must render through their
 *   relocated repo-root actions/templates (catches the @templates-vs-@core Twig
 *   namespace mixup, and the stale duplicate actions/listusers.php that used to
 *   shadow the class-based ListusersAction).
 * - LostPasswordAction::resetPassword() must now enforce the site's password
 *   policy (it never did before ticket 08 -- a real gap fixed on request).
 */
class LoginRelatedActionsTest extends YesWikiTestCase
{
    public function testWikiExisting(): Wiki
    {
        $wiki = $this->getWiki();
        $this->assertTrue($wiki->services->has(Wiki::class));

        return $wiki->services->get(Wiki::class);
    }

    public static function relocatedTagsProvider(): array
    {
        return [
            'login' => ['{{login}}'],
            'lostpassword' => ['{{lostpassword}}'],
            'listusers' => ['{{listusers}}'],
            'listusers with last' => ['{{listusers/last="5"}}'],
        ];
    }

    #[Depends('testWikiExisting')]
    #[DataProvider('relocatedTagsProvider')]
    public function testRelocatedTagRendersWithoutError(string $tag, Wiki $wiki)
    {
        $this->ensureCacheFolderIsWritable();
        $output = $wiki->Format($tag);
        $this->assertStringNotContainsStringIgnoringCase(
            'unable to find template',
            $output,
            "Tag $tag failed to resolve a twig template -- check for @templates vs @core namespace mixups."
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'no such table',
            $output,
            "Tag $tag hit a stale/shadowing action querying a dropped table."
        );
    }

    #[Depends('testWikiExisting')]
    public function testResetPasswordRejectsWeakPassword(Wiki $wiki)
    {
        $userManager = $wiki->services->get(UserManager::class);
        $users = $userManager->getAll();
        $this->assertNotEmpty($users, 'Need at least one existing user to test password recovery against.');
        $user = $users[0];

        // build a valid recovery key the same way UserManager::sendPasswordRecoveryEmail() does,
        // without going through it (that would also trigger a real send_mail() call)
        $passwordHasherFactory = $wiki->services->get(PasswordHasherFactory::class);
        $tripleStore = $wiki->services->get(TripleStore::class);
        $hasher = $passwordHasherFactory->getPasswordHasher($user);
        $plainKey = $user['name'] . '_recovery_test';
        $hashedKey = $hasher->hash($plainKey);
        $tripleStore->delete($user['name'], UserManager::KEY_VOCABULARY, null, '', '');
        $tripleStore->create($user['name'], UserManager::KEY_VOCABULARY, $hashedKey . UserManager::KEY_VALUE_SEPARATOR . time(), '', '');

        $action = new \LostPasswordAction();
        $action->setWiki($wiki);
        $reflection = new \ReflectionClass($action);
        foreach (['authenticationService' => AuthenticationService::class, 'inputFilter' => InputFilter::class, 'hibernationService' => HibernationService::class, 'tripleStore' => TripleStore::class, 'userManager' => UserManager::class] as $property => $serviceClass) {
            $prop = $reflection->getProperty($property);
            $prop->setValue($action, $wiki->services->get($serviceClass));
        }
        $resetPassword = $reflection->getMethod('resetPassword');

        $this->expectException(BadFormatPasswordException::class);
        try {
            $resetPassword->invoke($action, $user['name'], $hashedKey, 'a');
        } finally {
            $tripleStore->delete($user['name'], UserManager::KEY_VOCABULARY, null, '', '');
        }
    }

    /**
     * ensure the cache folder is writable before tests.
     */
    private function ensureCacheFolderIsWritable()
    {
        $this->assertTrue(is_dir('cache'), 'The cache folder is not existing !');
        $this->assertTrue(is_writable('cache'), 'The cache folder is not writable !');
    }
}
