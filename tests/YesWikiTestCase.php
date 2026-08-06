<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\TestCase;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\YesWikiRuntime;

class YesWikiTestCase extends TestCase
{
    /** the domains the suite's fixtures use -- nothing else is ever swept */
    private const TEST_EMAIL_DOMAINS = ['@example.com', '@example.tld', '@xyz.earth'];

    private static bool $leakSweepRegistered = false;

    /** start of the run, on the same clock signuptime is written with */
    private static string $runStartedAt = '';

    /** @var string[] groups that existed before the run, and so are none of its business */
    private static array $groupsBeforeRun = [];

    protected static function getWiki(): YesWikiRuntime
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
     *
     * Comments are swept the same way as users, on their timestamp: a test that posts one
     * leaves a page row behind on every single run, and CommentServiceHashcashTest alone
     * had accumulated 786 of them on one page. They are matched on `time` at or after the
     * run started, which in a test database is the suite's own writing.
     */
    private static function registerLeakSweep(YesWikiRuntime $wiki): void
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

            self::sweepComments($wiki);
        });
    }

    /** Every comment the run wrote, index rows included. */
    private static function sweepComments(YesWikiRuntime $wiki): void
    {
        try {
            $dbService = $wiki->services->get(DbService::class);
            $time = $dbService->quoteIdentifier('time');
            $rows = $dbService->loadAll(
                "SELECT DISTINCT tag FROM {$dbService->prefixTable('pages')}"
                . " WHERE parent <> '' AND {$time} >= '{$dbService->escape(self::$runStartedAt)}'"
            );
        } catch (\Throwable $t) {
            return;
        }

        $pageManager = $wiki->services->get(PageManager::class);
        $indexer = $wiki->services->get(SearchIndexer::class);
        foreach ($rows as $row) {
            $tag = (string)($row['tag'] ?? '');
            if ($tag === '') {
                continue;
            }
            try {
                $pageManager->deleteOrphaned($tag);
                $indexer->delete($tag);
            } catch (\Throwable $t) {
                // best effort, like the rest of the sweep
            }
        }
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

    /**
     * Narrow a nullable user lookup: tests that just created or fetched a user call this
     * to assert the lookup succeeded and get a non-null User for typed service calls.
     */
    /** @var int|null output-buffer depth at test start (see tearDown) */
    private $obLevelAtSetUp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->obLevelAtSetUp = ob_get_level();
    }

    protected function tearDown(): void
    {
        // a caught mid-render crash can leave the render pipeline's output buffer open;
        // the NEXT test's captured page then swallows stray output (debug SQL, warnings)
        // and fails on content it never produced. Normalize the depth between tests.
        while ($this->obLevelAtSetUp !== null && ob_get_level() > $this->obLevelAtSetUp) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    protected static function requireUser(?User $user): User
    {
        if ($user === null) {
            throw new \RuntimeException('expected an existing user');
        }

        return $user;
    }
}
