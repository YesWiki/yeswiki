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
    /** the domains the suite's fixtures use -- nothing else is ever swept. */
    private const TEST_EMAIL_DOMAINS = ['@example.com', '@example.tld', '@xyz.earth'];

    private static bool $leakSweepRegistered = false;

    /** start of the run, on the same clock signuptime is written with. */
    private static string $runStartedAt = '';

    /**
     * @var string[] groups that existed before the run, and so are none of its business
     */
    private static array $groupsBeforeRun = [];

    protected static function getWiki(): YesWikiRuntime
    {
        require_once 'src/YesWikiLoader.php';
        $wiki = YesWikiLoader::getWiki(true);
        self::registerLeakSweep($wiki);
        self::pinExperimentalSwitches($wiki);

        return $wiki;
    }

    /**
     * The suite reads the developer's own yeswiki.config.php (test.config.php has not been the one for a while), so anything they switch on to try it out changes what the tests see.
     */
    private static function pinExperimentalSwitches(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class)['vditor_wiki_editor'] = false;
    }

    /** Deletes the users and groups the run created, at process shutdown. */
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
                }
            }

            $groupManager = $wiki->services->get(GroupManager::class);
            foreach (array_diff($groupManager->getall(), self::$groupsBeforeRun) as $group) {
                try {
                    $groupManager->delete($group);
                } catch (\Throwable $t) {
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
     * @var int|null output-buffer depth at test start (see tearDown)
     */
    private $obLevelAtSetUp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->obLevelAtSetUp = ob_get_level();
    }

    protected function tearDown(): void
    {
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
