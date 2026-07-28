<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Wiki;

class YesWikiTestCase extends TestCase
{
    /** the domains the suite's fixtures use -- nothing else is ever swept */
    private const TEST_EMAIL_DOMAINS = ['@example.com', '@example.tld', '@xyz.earth'];

    private static bool $leakSweepRegistered = false;

    /** start of the run, on the same clock signuptime is written with */
    private static string $runStartedAt = '';

    /** @var string[] groups that existed before the run, and so are none of its business */
    private static array $groupsBeforeRun = [];

    protected static function getWiki(): Wiki
    {
        require_once 'src/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki(true);
        self::registerLeakSweep($wiki);

        return $wiki;
    }

    /**
     * Deletes the users and groups the run created, at process shutdown.
     *
     * Per-class teardown cannot: several fixtures are created by static data providers,
     * which PHPUnit resolves before setUpBeforeClass() and even for tests that end up
     * skipped. And since users became page rows, every leaked one is a page row too -- a
     * few days of local runs had left 2226 users and 3352 groups behind, against 310 real
     * pages and one real group.
     *
     * Users are matched on a test email domain AND a signuptime at or after the run
     * started, so a database that legitimately holds an @example.com account from before
     * the run keeps it. Groups carry no timestamp, so they are diffed against a snapshot
     * taken here instead -- this runs on the first getWiki() call, which is the first thing
     * any provider does, hence before the run has created any group of its own.
     */
    private static function registerLeakSweep(Wiki $wiki): void
    {
        if (self::$leakSweepRegistered) {
            return;
        }
        self::$leakSweepRegistered = true;
        self::$runStartedAt = date('Y-m-d H:i:s');
        self::$groupsBeforeRun = $wiki->services->get(GroupManager::class)->getall();

        register_shutdown_function(static function () use ($wiki): void {
            $userManager = $wiki->services->get(UserManager::class);
            foreach ($userManager->getAll() as $user) {
                if (!self::isLeakedTestUser($user)) {
                    continue;
                }
                try {
                    $userManager->delete($user);
                } catch (\Throwable $t) {
                    // best effort: a sweep failure must not turn a passing run red
                }
            }

            $groupManager = $wiki->services->get(GroupManager::class);
            foreach (array_diff($groupManager->getall(), self::$groupsBeforeRun) as $group) {
                try {
                    $groupManager->delete($group);
                } catch (\Throwable $t) {
                    // idem
                }
            }
        });
    }

    private static function isLeakedTestUser(mixed $user): bool
    {
        $signupTime = (string)($user['signuptime'] ?? '');
        if ($signupTime === '' || $signupTime < self::$runStartedAt) {
            return false;
        }

        $email = (string)($user['email'] ?? '');
        foreach (self::TEST_EMAIL_DOMAINS as $domain) {
            if (str_ends_with($email, $domain)) {
                return true;
            }
        }

        return false;
    }
}
