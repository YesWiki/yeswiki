<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Kernel\Service\DatabaseClock;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTransportTest.php';

/** The queue goes by the database's clock, in UTC whatever the session's zone: the machines of a farm need not agree on theirs. */
class DatabaseClockTest extends YesWikiTestCase
{
    /** How far the database's clock may be from PHP's in the development containers, which share the host's. */
    private const DRIFT_SECONDS = 5;

    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null) {
            @unlink($this->file);
        }
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, string|bool>}>
     */
    public static function servers(): array
    {
        return ['sqlite' => [['db_driver' => 'sqlite', 'table_prefix' => 'ywclock_', 'debug' => false]]] + QueueTransportTest::realServers();
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testItTellsTheDatabasesTimeInUtcWhateverTheSessionsZone(array $params): void
    {
        $db = $this->open($params);
        match ($db->getDriver()) {
            'mysql' => $db->query("SET time_zone = '+05:00'"),
            'pgsql' => $db->query("SET TIME ZONE INTERVAL '+05:00' HOUR TO MINUTE"),
            default => null,
        };

        $now = (new DatabaseClock($db))->now();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertEqualsWithDelta(time(), $now->getTimestamp(), self::DRIFT_SECONDS, 'the session is five hours ahead, the clock is not');
    }

    /** @param array<string, string|bool> $params */
    #[DataProvider('servers')]
    public function testItMovesOnWithinATransaction(array $params): void
    {
        $db = $this->open($params);
        $clock = new DatabaseClock($db);

        [$first, $second] = $db->transactional(static function () use ($clock): array {
            $first = $clock->now();
            usleep(20000);

            return [$first, $clock->now()];
        });

        $this->assertGreaterThan($first, $second, 'a transaction may last: the time it reads must not stand still');
    }

    /** @param array<string, string|bool> $params */
    private function open(array $params): DbService
    {
        if ($params['db_driver'] !== 'sqlite' && !QueueTransportTest::reachable($params)) {
            $this->markTestSkipped("no {$params['db_driver']} server at {$params['db_host']}");
        }
        if ($params['db_driver'] === 'sqlite') {
            $this->file = tempnam(sys_get_temp_dir(), 'yw-clock-') . '.db';
            $params['db_database'] = $this->file;
        }

        return new DbService(new ParameterBag($params));
    }
}
