<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use YesWiki\Admin\Command\MaintenanceCommand;
use YesWiki\Core\YesWikiRuntime;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';
require_once 'tests/src/Kernel/QueueTestKit.php';

/**
 * What is left of the housekeeping sweep: `core:maintenance`, a deprecated alias of `messenger:drain` so that existing crontabs keep working, and no page view sweeping any more.
 */
#[CoversClass(MaintenanceCommand::class)]
class MaintenanceCommandTest extends YesWikiTestCase
{
    private const RETIRED_LOCK = 'cache/maintenance.lock';

    private ?QueueTestKit $kit = null;

    protected function tearDown(): void
    {
        $this->kit?->dispose();
    }

    public function testCoreMaintenanceDrainsAndWarns(): void
    {
        $this->kit = new QueueTestKit();
        $this->kit->send('a');
        $this->kit->send('b');

        $tester = $this->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a', 'b'], $this->kit->handled, 'it consumes the queue');
        $this->assertStringContainsString('handled 2', $tester->getDisplay(), 'and says so the way messenger:drain does');
        $warning = trim($tester->getErrorOutput());
        $this->assertSame(1, substr_count($warning, "\n") + 1, 'one line on stderr');
        $this->assertStringContainsString('deprecated', $warning);
        $this->assertStringContainsString('messenger:drain', $warning, 'naming what to call instead');
    }

    public function testItExitsAsTheDrainDoes(): void
    {
        $this->kit = new QueueTestKit();
        $this->kit->dropTables();

        $this->assertSame(Command::FAILURE, $this->execute([])->getStatusCode(), 'cron still mails the admin about a broken pass');
    }

    public function testAnOldCrontabWithForceStillRuns(): void
    {
        $this->kit = new QueueTestKit();
        $this->kit->send('a');

        $tester = $this->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame(['a'], $this->kit->handled);
    }

    public function testNoPageViewSweepsAnymore(): void
    {
        $this->assertFalse(method_exists(YesWikiRuntime::class, 'maintenance'), 'the wiki has no sweep to run');

        // The lock is a leftover of the retired sweep: whatever is there is no longer anybody's.
        @unlink(self::RETIRED_LOCK);
        $wiki = $this->getWiki();
        ob_start();
        try {
            $wiki->run('PagePrincipale', 'show');
        } finally {
            ob_end_clean();
        }

        clearstatcache();
        $this->assertFileDoesNotExist(self::RETIRED_LOCK, 'a page view stamped the maintenance interval');
    }

    /** @param array<string, mixed> $options */
    private function execute(array $options): CommandTester
    {
        $settings = new QueueSettings(new ParameterBag([]), static fn (): array => []);
        $application = new Application();
        $application->add(new MaintenanceCommand($this->kit()->services($settings)));
        $tester = new CommandTester($application->find('core:maintenance'));
        $tester->execute(['command' => 'core:maintenance'] + $options, ['capture_stderr_separately' => true]);

        return $tester;
    }

    private function kit(): QueueTestKit
    {
        return $this->kit ?? throw new \LogicException('no queue for this test');
    }
}
