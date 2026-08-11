<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Content\Service\PageManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for second-order SQL injection in AclService::updateRequestWithACL().
 *
 * A self-registered user's name is only restricted by UserOperationsService::PATTERN_USER_NAME,
 * which allows double-quote, space, '(' and ')' (it only excludes ! # @ < > \ /). The raw
 * name is escaped when INSERTed (UserManager::create), so it's stored verbatim, including
 * any '"'. updateRequestWithACL() then reloads that name and used to concatenate it raw
 * into ' list LIKE "%<name>%"' / ' list NOT LIKE "%!<name>%"'.
 *
 * A name like 'x" OR SLEEP(1) OR "y' breaks out of a *double*-quoted LIKE string with no
 * need for any backslash/quote-parity trick: it's a direct, unescaped '"' reaching a string
 * literal. A first attempt at fixing this just wrapped the value in DbService::escape()
 * (PDO::quote()) without changing the surrounding literal's quote style -- which turned out
 * to be driver-dependent: MySQL's PDO driver happens to also backslash-escape '"' (so that
 * was incidentally safe there), but SQLite's PDO driver never touches '"' at all (PDO::quote()
 * only guarantees safety for the *single*-quoted literal it wraps its own output in), so the
 * same "fixed" code was still exploitable on SQLite. The actual fix switches these LIKE
 * clauses to single-quoted SQL literals, matching what escape() actually protects, portable
 * across every driver. Verified black-box the same way this was originally verified against
 * a real MariaDB connection: the generated fragment, dropped into the real
 * getReadablePageTags()-style query, executes SLEEP() (confirmed via SHOW PROCESSLIST / query
 * timing) before the fix, and behaves as an inert literal LIKE pattern after it.
 */
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

        // second-order storage: the raw '"' survives into the users table (escaped only
        // for the INSERT statement itself, not sanitized away)
        $userManager->create(self::MALICIOUS_NAME, self::TEST_EMAIL, 'Aa1!aaaaRegression');

        // simulate this user being logged in, the way AclService::updateRequestWithACL()
        // observes it (via AuthenticationService::getLoggedUser() -> $_SESSION['user'])
        $previousSessionUser = $_SESSION['user'] ?? null;
        $_SESSION['user'] = ['name' => self::MALICIOUS_NAME, 'lastConnection' => time()];

        try {
            $fragment = $aclService->updateRequestWithACL();

            // Since ticket 31 the predicate is a SqlFragment, so the strongest available
            // statement is no longer "the payload is escaped" but "the payload is not in the
            // statement". Assert that: the name appears nowhere in the SQL text, and appears
            // in the bound values instead. No quoting rule of any driver applies to it.
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

            // Kept from the original: what matters is that the payload cannot execute. This was
            // first verified against a real MariaDB connection by timing SLEEP(1); the timing
            // check stays because it is behavioural rather than a claim about the SQL's shape.
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

    /**
     * The predicate must still *filter*.
     *
     * This is the failure mode that matters and the one that does not look like an error: a
     * read-ACL predicate that quietly becomes `1 = 1` -- or loses its values, or gets composed
     * away by an `isEmpty()` branch taken by mistake -- returns more rows rather than fewer, and
     * every test asserting "the page I created is findable" still passes. Ticket 31 moved this
     * predicate through three composition steps, which is exactly the refactor that can do it.
     */
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
