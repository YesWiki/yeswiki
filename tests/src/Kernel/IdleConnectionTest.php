<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\Lock\DatabaseLocks;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/** Replicas 19: a worker gives its connection back between requests, unless a transaction or a lock still needs it. */
class IdleConnectionTest extends YesWikiTestCase
{
    private function db(): DbService
    {
        return new DbService(new ParameterBag(self::getWiki()->services->get(ParameterBagInterface::class)->all()));
    }

    public function testAnIdleConnectionIsClosedAndTheNextStatementOpensOne(): void
    {
        $db = $this->db();
        $this->assertSame('1', (string)$db->scalar('SELECT 1'));

        $this->assertTrue($db->disconnectIfIdle());
        $this->assertFalse($db->isConnected());

        $this->assertSame('2', (string)$db->scalar('SELECT 2'));
        $this->assertTrue($db->isConnected());
    }

    public function testNothingToCloseIsNotAFailure(): void
    {
        $db = new DbService(new ParameterBag(self::getWiki()->services->get(ParameterBagInterface::class)->all()), false);

        $this->assertTrue($db->disconnectIfIdle());
        $this->assertFalse($db->isConnected(), 'closing never opens one first');
    }

    public function testAnOpenTransactionKeepsItsConnection(): void
    {
        $db = $this->db();
        $db->transactional(function () use ($db): void {
            $this->assertFalse($db->disconnectIfIdle());
            $this->assertTrue($db->isConnected());
        });

        $this->assertTrue($db->disconnectIfIdle());
    }

    public function testAHeldLockKeepsItsConnectionUntilReleased(): void
    {
        if ($this->db()->getDriver() === 'sqlite') {
            $this->markTestSkipped('SQLite locks are files, not the connection');
        }
        $db = $this->db();
        $locks = new DatabaseLocks($db, self::getWiki()->services->get(ParameterBagInterface::class));
        $name = 'idle-connection-test-' . bin2hex(random_bytes(4));

        $this->assertTrue($locks->tryAcquire($name));
        $this->assertFalse($db->disconnectIfIdle(), 'closing would drop the lock with the connection');

        $locks->release($name);
        $this->assertTrue($db->disconnectIfIdle());
    }
}
