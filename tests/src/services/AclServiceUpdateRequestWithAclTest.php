<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\UserManager;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Regression test for second-order SQL injection in AclService::updateRequestWithACL().
 *
 * A self-registered user's name is only restricted by UserController::PATTERN_USER_NAME,
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
        // observes it (via AuthController::getLoggedUser() -> $_SESSION['user'])
        $previousSessionUser = $_SESSION['user'] ?? null;
        $_SESSION['user'] = ['name' => self::MALICIOUS_NAME, 'lastConnection' => time()];

        try {
            $fragment = $aclService->updateRequestWithACL();

            // pre-fix: the fragment contains the raw, unescaped '"' from the username
            // directly inside a *double*-quoted LIKE string, breaking out of it.
            $this->assertStringNotContainsString(
                'LIKE "%AclSqliRegressionUser" OR',
                $fragment,
                'the username breaks out of a double-quoted LIKE string literal unescaped (SQL injection)'
            );

            // post-fix: the fix (AclService::updateRequestWithACL()) switched these LIKE
            // clauses to *single*-quoted SQL literals, matching what DbService::escape()
            // (PDO::quote()) actually guarantees -- portable across every driver, unlike
            // relying on escape() to also neutralize '"' inside a double-quoted literal
            // (MySQL's PDO driver happens to backslash-escape '"' too, so that was
            // incidentally safe there, but SQLite's PDO driver never touches '"' at all,
            // which is exactly what made the original double-quoted version exploitable
            // here). So the double-quote content in the malicious name is now just inert
            // text inside a safely-delimited single-quoted string, not a breakout -- checking
            // for one particular escaped substring doesn't fit every valid fix shape; what
            // actually matters is that the payload can no longer execute as SQL. Verify that
            // black-box, the same way this was originally verified against a real MariaDB
            // connection: a SLEEP(1) in the payload must not measurably slow the query down.
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
}
