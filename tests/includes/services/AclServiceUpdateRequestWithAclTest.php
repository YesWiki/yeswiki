<?php

namespace YesWiki\Test\Core\Service;

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
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
 * into ' list LIKE "%<name>%"' / ' list NOT LIKE "%!<name>%"' (includes/services/AclService.php),
 * while the sibling owner branch a few lines below correctly escaped the same value via
 * DbService::escape() (owner = _utf8'...') -- an escape-one-branch-not-the-other asymmetry.
 *
 * A name like 'x" OR SLEEP(1) OR "y' breaks out of the LIKE string with no need for any
 * backslash/quote-parity trick (unlike some other injection points in this codebase) : it's a
 * direct, unescaped '"' reaching a string literal. Verified live against a real MariaDB
 * connection: the generated fragment, dropped into the real getReadablePageTags()-style query,
 * executes SLEEP() (confirmed via SHOW PROCESSLIST / query timing) before the fix, and behaves
 * as an inert literal LIKE pattern after it.
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
            // directly inside the LIKE string, breaking out of it.
            // post-fix: that '"' is escaped (backslash-prefixed) before being wrapped.
            $this->assertStringNotContainsString(
                'LIKE "%AclSqliRegressionUser" OR',
                $fragment,
                'the username breaks out of the LIKE string literal unescaped (SQL injection)'
            );
            $this->assertStringContainsString(
                'AclSqliRegressionUser\\" OR SLEEP(1) OR \\"1',
                $fragment,
                'the username\'s quote should be escaped before being wrapped into the SQL LIKE clause'
            );

            // sanity check : the fragment must still be valid, executable SQL (the fix
            // must not break the ACL filter for a page-listing query)
            $tags = $pageManager->getReadablePageTags();
            $this->assertIsArray($tags);
        } finally {
            if ($previousSessionUser === null) {
                unset($_SESSION['user']);
            } else {
                $_SESSION['user'] = $previousSessionUser;
            }
            $wiki->services->get(DbService::class)->query(
                'DELETE FROM ' . $wiki->config['table_prefix'] . "users WHERE email = '" . self::TEST_EMAIL . "'"
            );
        }
    }
}
