<?php

namespace YesWiki\Test\Core;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiLoader;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\BotGuard;
use YesWiki\Identity\Service\GroupManager;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Journal;
use YesWiki\Kernel\Service\JournalSchema;
use YesWiki\Kernel\Service\Queue\QueueSchema;
use YesWiki\Kernel\Service\Queue\QueueTransport;
use YesWiki\Search\Message\ReindexContent;
use YesWiki\Search\Service\SearchIndexer;

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
        self::keepTheLogOffTheSuitesOwnStream($wiki);

        return $wiki;
    }

    /**
     * The Journal writes every event to stderr, unconditionally, which is the half of ADR-0025 that survives a database being down -- and `phpunit --stderr` reports on the same stream, so a run of the suite would be one line of its own per hundred of the wiki's.
     *
     * The stream is a seam, not a switch: this points it at memory rather than turning it off,
     * and a test that wants to read the log back points it at its own.
     */
    private static function keepTheLogOffTheSuitesOwnStream(YesWikiRuntime $wiki): void
    {
        $sink = fopen('php://memory', 'w+b');
        if ($sink !== false) {
            $wiki->services->get(Journal::class)->writeTo($sink);
        }
    }

    /**
     * The suite reads the developer's own yeswiki.config.php (test.config.php has not been the one for a while), so anything they switch on to try it out changes what the tests see.
     */
    private static function pinExperimentalSwitches(YesWikiRuntime $wiki): void
    {
        $config = $wiki->services->get(\YesWiki\Kernel\Service\RuntimeConfig::class);
        $config['vditor_wiki_editor'] = false;
        // A page view must never consume the queue underneath a test that is draining it. The
        // end-of-request pass is registered by index.php and worker.php, which the suite does not
        // go through, and QueueSettings reads the compiled parameters, which cannot be changed
        // here: this keeps what the suite's configuration says in line with that.
        $config['queue_consumer'] = \YesWiki\Kernel\Queue\QueueConsumerMode::Command->value;
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
            $swept = [];
            $userManager = $wiki->services->get(UserManager::class);
            foreach ($userManager->getAll() as $user) {
                if (!self::isLeakedTestUser($user)) {
                    continue;
                }
                try {
                    $userManager->delete($user);
                    $swept[] = (string)$user['name'];
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
            self::sweepJournal($wiki, $swept);
        });
    }

    /**
     * Every Journal entry the run wrote.
     *
     * The suite creates and deletes real Content, so the Journal records real acts -- roughly a
     * thousand of them per run, in the developer's own wiki, where the point of an audit trail is
     * that it is worth reading.
     *
     * Narrowed to two kinds of actor: none at all, which is what a CLI process writes, and the
     * fixture accounts this sweep has just deleted, whose acts were about accounts that no longer
     * exist. Anything the developer did in a browser while the suite ran carries their own name
     * and is left alone.
     *
     * @param list<string> $sweptUsers the fixture accounts this run created and this sweep removed
     */
    private static function sweepJournal(YesWikiRuntime $wiki, array $sweptUsers): void
    {
        try {
            $dbService = $wiki->services->get(DbService::class);
            $table = $dbService->quoteIdentifier($wiki->services->get(JournalSchema::class)->table());
            $actor = $dbService->quoteIdentifier('actor');
            $at = $dbService->quoteIdentifier('at');

            $actors = ['', ...$sweptUsers];
            $placeholders = SqlParameters::placeholders(count($actors));

            $dbService->query(
                "DELETE FROM {$table} WHERE {$actor} IN ({$placeholders}) AND {$at} >= ?",
                [...$actors, self::$runStartedAt]
            );

            // And the acts the suite performed as the developer's own admin account, which is who
            // most of its fixtures sign in as. Narrowed to Content that is no longer there: the
            // suite deletes everything it makes, so a page still standing was somebody's real
            // work. A real deletion made in a browser *while the suite ran* is swept with the
            // rest, which is the one case this cannot tell apart and is a dev wiki's to lose.
            $dbService->query(
                "DELETE FROM {$table} WHERE {$at} >= ?"
                . ' AND ' . $dbService->quoteIdentifier('action') . " LIKE 'content.%'"
                . " AND {$dbService->quoteIdentifier('target')} NOT IN ("
                . "SELECT tag FROM {$dbService->prefixTable('pages')})",
                [self::$runStartedAt]
            );
        } catch (\Throwable $t) {
        }
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

    /**
     * The address is the whole test, deliberately: no signuptime window.
     *
     * A run that dies on a fatal -- an out-of-memory, a segfault -- takes this sweep down with it
     * and strands everything it had created. Gating on "created since this run started" meant
     * those accounts were nobody's business ever again, and they piled up in the developer's own
     * wiki one crashed run at a time. Sweeping by address alone makes the next run clean them up.
     * Nothing real is at risk: example.com and example.tld are reserved for documentation, and
     * xyz.earth is this suite's own invention.
     */
    private static function isLeakedTestUser(mixed $user): bool
    {
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

    /** @var list<string> directories makeDirectory() created, deepest first */
    private array $createdDirectories = [];

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
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        foreach ($this->createdDirectories as $directory) {
            @rmdir($directory);
        }
        $this->createdDirectories = [];
        parent::tearDown();
    }

    /**
     * Drops what the test's own properties hold, once its tearDown() has run.
     *
     * PHPUnit keeps every test object until the end of the run, and with it every database
     * connection a fixture opened: a throw-away SQLite file is unlinked, but its three
     * descriptors stay open. Past 1024 of them, a new pipe gets a descriptor select() cannot
     * watch, and every subprocess the suite starts (ConsoleService) reads back as having said
     * nothing. Only the properties a test class declares are dropped, not PHPUnit's own, and not
     * readonly ones, which cannot be.
     */
    #[After(-1000)]
    protected function releaseWhatTheTestHeld(): void
    {
        for ($class = new \ReflectionClass($this); $class !== false && $class->getName() !== TestCase::class; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || $property->isReadOnly() || $property->getDeclaringClass()->getName() !== $class->getName() || !$property->isInitialized($this)) {
                    continue;
                }
                $name = $property->getName();
                \Closure::bind(function () use ($name): void {
                    unset($this->{$name});
                }, $this, $class->getName())();
            }
        }
        gc_collect_cycles();
    }

    /** Creates a directory and its missing parents, which tearDown() removes again once emptied. */
    protected function makeDirectory(string $path): void
    {
        $missing = [];
        for ($directory = rtrim($path, '/'); $directory !== '' && $directory !== '.' && !is_dir($directory); $directory = \dirname($directory)) {
            $missing[] = $directory;
        }
        if ($missing !== [] && !mkdir($path, 0o775, true) && !is_dir($path)) {
            throw new \RuntimeException("cannot create {$path}");
        }
        $this->createdDirectories = [...$missing, ...$this->createdDirectories];
    }

    /**
     * Guard fields that pass BotGuard, with ALTCHA off until restoreBotGuard().
     *
     * @return array<string, string>
     */
    protected static function validBotGuardFields(YesWikiRuntime $wiki): array
    {
        $botGuard = $wiki->services->get(BotGuard::class);
        $botGuard->useForTests(null, fn () => time() - 10, false);
        preg_match_all('/<input type="(?:hidden|text)"[^>]*name="([^"]+)" value="([^"]*)"/', $botGuard->fields(), $inputs, PREG_SET_ORDER);
        $botGuard->useForTests(null, null, false);

        return array_column($inputs, 2, 1);
    }

    /** Undoes validBotGuardFields(). */
    protected static function restoreBotGuard(YesWikiRuntime $wiki): void
    {
        $wiki->services->get(BotGuard::class)->useForTests();
    }

    /**
     * Runs the ReindexContent messages waiting in the queue, and only those, the way the consumer does: what a test that reads the index after a save needs first.
     *
     * @return int how many were run
     */
    protected static function reindexWaitingContents(YesWikiRuntime $wiki): int
    {
        $db = $wiki->services->get(DbService::class);
        $transport = $wiki->services->get(QueueTransport::class);
        $bus = $wiki->services->get(MessageBusInterface::class);
        $ids = $db->loadAll(
            "SELECT id FROM {$wiki->services->get(QueueSchema::class)->table()}"
            . ' WHERE queue_name = ? AND message_type = ? AND delivered_at IS NULL ORDER BY id',
            [QueueTransport::DEFAULT_QUEUE, ReindexContent::class]
        );

        $run = 0;
        foreach ($ids as $row) {
            $envelope = $transport->getById((int)$row['id']);
            if ($envelope === null) {
                continue;
            }
            $bus->dispatch($envelope->with(new ReceivedStamp(QueueTransport::DEFAULT_QUEUE)));
            $transport->ack($envelope);
            $run++;
        }

        return $run;
    }

    protected static function requireUser(?User $user): User
    {
        if ($user === null) {
            throw new \RuntimeException('expected an existing user');
        }

        return $user;
    }
}
