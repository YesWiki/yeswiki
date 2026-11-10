<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Lock\DatabaseLocks;
use YesWiki\Kernel\Service\Lock\Locks;
use YesWiki\Kernel\Service\Queue\QueueHolderLock;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 03: a lock of the wiki excludes every connection to its database, not only the processes of one machine. */
#[CoversClass(DatabaseLocks::class)]
class DatabaseLocksTest extends YesWikiTestCase
{
    private ParameterBagInterface $params;

    private string $name;

    protected function setUp(): void
    {
        parent::setUp();
        $this->params = self::getWiki()->services->get(ParameterBagInterface::class);
        $this->name = 'test-' . bin2hex(random_bytes(6));
    }

    public function testTheWikiResolvesItsLocksToTheDatabase(): void
    {
        $this->assertInstanceOf(DatabaseLocks::class, self::getWiki()->services->get(Locks::class));
    }

    public function testALockHeldByOneConnectionIsRefusedToAnother(): void
    {
        $first = $this->connection();
        $second = $this->connection();

        $this->assertTrue($first->tryAcquire($this->name));
        $heldElsewhere = false;
        $this->assertFalse($second->tryAcquire($this->name, $heldElsewhere));
        $this->assertTrue($heldElsewhere, 'the refusal names another holder');
        $this->assertTrue($second->probe($this->name), 'the other connection sees it held');

        $first->release($this->name);
        $this->assertFalse($second->probe($this->name));
        $this->assertTrue($second->tryAcquire($this->name), 'and takes it once it is let go');
        $second->release($this->name);
    }

    public function testALockGoesWithTheConnectionThatHeldIt(): void
    {
        $holder = $this->connection();
        $this->assertTrue($holder->tryAcquire($this->name));
        unset($holder);
        gc_collect_cycles();

        $other = $this->connection();
        $this->assertFalse($other->probe($this->name));
        $this->assertTrue($other->tryAcquire($this->name));
        $other->release($this->name);
    }

    public function testOneHolderCannotTakeItsOwnLockTwice(): void
    {
        $locks = $this->connection();
        $this->assertTrue($locks->tryAcquire($this->name));
        $heldElsewhere = false;
        $this->assertFalse($locks->tryAcquire($this->name, $heldElsewhere));
        $this->assertTrue($heldElsewhere);

        $locks->release($this->name);
        $this->assertFalse($this->connection()->probe($this->name), 'one release lets it go');
    }

    public function testTwoWikisOnOneServerDoNotShareALock(): void
    {
        if ($this->params->get('db_driver') === 'sqlite') {
            $this->markTestSkipped('a SQLite wiki has its database to itself');
        }
        $prefix = $this->params->get('table_prefix');
        $other = new ParameterBag(['table_prefix' => 'other_' . (is_string($prefix) ? $prefix : '')] + $this->params->all());
        $mine = $this->connection();
        $theirs = new DatabaseLocks(new DbService($other), $other);

        $this->assertTrue($mine->tryAcquire($this->name));
        $this->assertTrue($theirs->tryAcquire($this->name), 'the same name in another wiki is another lock');
        $mine->release($this->name);
        $theirs->release($this->name);
    }

    public function testAPassHolderIsSeenFromAnotherConnection(): void
    {
        $pass = new QueueHolderLock($this->connection());
        $judge = new QueueHolderLock($this->connection());
        $token = QueueHolderLock::newToken();

        $this->assertTrue($pass->hold($token));
        $this->assertSame(QueueHolderLock::HELD, $judge->stateOf($token));

        $pass->release($token);
        $this->assertSame($this->params->get('db_driver') === 'sqlite' ? QueueHolderLock::ABSENT : QueueHolderLock::FREE, $judge->stateOf($token));
    }

    private function connection(): DatabaseLocks
    {
        return new DatabaseLocks(new DbService($this->params), $this->params);
    }
}
