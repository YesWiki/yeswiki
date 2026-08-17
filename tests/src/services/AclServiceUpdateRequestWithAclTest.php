<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Regression test for second-order SQL injection in AclService::updateRequestWithACL(). */
class AclServiceUpdateRequestWithAclTest extends YesWikiTestCase
{
    private const MALICIOUS_NAME = 'AclSqliRegressionUser" OR SLEEP(1) OR "1';
    private const TEST_EMAIL = 'aclsqliregression@example.tld';
    private const PRIVATE_TAG = 'AclPredicateRegressionPrivate';
    private const PUBLIC_TAG = 'AclPredicateRegressionPublic';

    public function testMaliciousUserNameIsEscapedInGeneratedAclFragment()
    {
        $wiki = $this->getWiki();
        $userManager = $wiki->services->get(UserManager::class);
        $aclService = $wiki->services->get(AclService::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $userManager->create(self::MALICIOUS_NAME, self::TEST_EMAIL, 'Aa1!aaaaRegression');

        $previousSessionUser = $_SESSION['user'] ?? null;
        $_SESSION['user'] = ['name' => self::MALICIOUS_NAME, 'lastConnection' => time()];

        try {
            $fragment = $aclService->updateRequestWithACL();

            $this->assertStringNotContainsString(
                'AclSqliRegressionUser',
                $fragment->sql,
                'the username must not reach the statement text at all -- it is a bound value'
            );
            $this->assertStringNotContainsString('SLEEP', $fragment->sql);
            $this->assertNotEmpty(
                array_filter(
                    $fragment->params,
                    static fn ($value): bool => is_string($value) && str_contains($value, 'AclSqliRegressionUser')
                ),
                'and it must actually be among the bound values, or the predicate stopped using it'
            );

            $start = microtime(true);
            $tags = $pageManager->getReadablePageTags();
            $elapsed = microtime(true) - $start;

            $this->assertIsArray($tags);
            $this->assertLessThan(
                0.9,
                $elapsed,
                "getReadablePageTags() took {$elapsed}s: looks like the injected SLEEP(1) executed."
            );
        } finally {
            if ($previousSessionUser === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $previousSessionUser;
            }
            $createdUser = $userManager->getOneByEmail(self::TEST_EMAIL);
            if ($createdUser) {
                $userManager->delete($createdUser);
            }
        }
    }

    /** The predicate must still *filter*. */
    public function testThePredicateActuallyRestrictsWhatAnAnonymousVisitorSees(): void
    {
        $wiki = $this->getWiki();
        $aclService = $wiki->services->get(AclService::class);
        $pageManager = $wiki->services->get(PageManager::class);

        $previousSessionUser = $_SESSION['user'] ?? null;
        unset($_SESSION['user']);

        try {
            $pageManager->save(self::PRIVATE_TAG, [\YesWiki\Content\Entity\PageBody::CONTENT => 'secret'], '', true);
            $aclService->save(self::PRIVATE_TAG, 'read', '@' . ADMIN_GROUP);
            $pageManager->save(self::PUBLIC_TAG, [\YesWiki\Content\Entity\PageBody::CONTENT => 'public'], '', true);
            $aclService->save(self::PUBLIC_TAG, 'read', '*');
            $pageManager->forget(self::PRIVATE_TAG);
            $pageManager->forget(self::PUBLIC_TAG);

            $fragment = $aclService->updateRequestWithACL();
            $this->assertFalse($fragment->isEmpty(), 'an anonymous visitor must get a predicate, not nothing');
            $this->assertStringNotContainsString('1 = 1', $fragment->sql);

            $readable = $pageManager->getReadablePageTags();

            $this->assertContains(self::PUBLIC_TAG, $readable, 'a public page must stay readable');
            $this->assertNotContains(
                self::PRIVATE_TAG,
                $readable,
                'an admins-only page must NOT be listed for an anonymous visitor -- if this fails the '
                . 'predicate stopped filtering, which no other assertion in the suite would notice'
            );
        } finally {
            if ($previousSessionUser === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $previousSessionUser;
            }
            $pageManager->deleteOrphaned(self::PRIVATE_TAG);
            $pageManager->deleteOrphaned(self::PUBLIC_TAG);
        }
    }
}
