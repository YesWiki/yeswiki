<?php

namespace YesWiki\Test\Content;

use PHPUnit\Framework\Attributes\CoversMethod;
use YesWiki\Content\Command\ContactDigestCommand;
use YesWiki\Content\Service\ContactDigestScheduler;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * Ticket 35: the digests, on a wiki with no cron.
 *
 * The rule worth testing is the one that cannot be undone. A period is claimed -- its state file
 * stamped -- *before* anything is sent, because two page views crossing the maintenance interval
 * together must not both start the daily digest, and unlike a repeated import, a repeated digest is
 * a second email in somebody's inbox. Claiming afterwards would be the natural way to write it and
 * would be wrong.
 */
#[CoversMethod(ContactDigestScheduler::class, 'claimDuePeriods')]
class ContactDigestSchedulerTest extends YesWikiTestCase
{
    /** @var list<string> */
    private array $stamped = [];

    protected function setUp(): void
    {
        // start from "never sent", whatever this wiki has done before
        foreach (ContactDigestCommand::PERIODS as $period) {
            $file = $this->stateFile($period);
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->stamped as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->stamped = [];
    }

    private function stateFile(string $period): string
    {
        // \YESWIKI_INSTANCE_DIR: this file is namespaced, so an unqualified constant is looked
        // up in the namespace first and then fails rather than falling back to the global one
        self::getWiki();
        $file = \YESWIKI_INSTANCE_DIR . '/private/digests/' . $period . '.last';
        $this->stamped[] = $file;

        return $file;
    }

    private function scheduler(): ContactDigestScheduler
    {
        return $this->getWiki()->services->get(ContactDigestScheduler::class);
    }

    public function testEveryPeriodIsDueOnAWikiThatHasNeverSentOne(): void
    {
        $this->assertSame(ContactDigestCommand::PERIODS, $this->scheduler()->claimDuePeriods());
    }

    /**
     * The claim, which is the whole point: a second pass immediately afterwards must find nothing,
     * or a wiki busy enough to cross the interval twice mails everybody twice.
     */
    public function testASecondPassImmediatelyAfterwardsClaimsNothing(): void
    {
        $first = $this->scheduler()->claimDuePeriods();
        $this->assertNotSame([], $first, 'fixture: something must have been due');

        $this->assertSame(
            [],
            $this->scheduler()->claimDuePeriods(),
            'claiming happens before sending, so the same period must not come up again'
        );
    }

    public function testAPeriodBecomesDueAgainOnceItsFloorHasPassed(): void
    {
        $this->scheduler()->claimDuePeriods();

        // the daily floor is 23h; back-date past it and only that period should return
        $day = $this->stateFile('day');
        $this->assertFileExists($day, 'fixture: the claim must have stamped a file');
        touch($day, time() - 86400);

        $this->assertSame(['day'], $this->scheduler()->claimDuePeriods());
    }

    /** Each period is stamped separately, or one send would suppress the others. */
    public function testClaimingStampsOnlyTheClaimedPeriods(): void
    {
        $this->scheduler()->claimDuePeriods();

        foreach (ContactDigestCommand::PERIODS as $period) {
            $this->assertFileExists($this->stateFile($period), "{$period} should have been stamped");
        }
    }

    /**
     * The state lives under private/, not cache/. A cache is something anybody may delete to fix a
     * problem, and deleting this one sends every digest again.
     */
    public function testTheStateIsNotKeptInTheCache(): void
    {
        $this->scheduler()->claimDuePeriods();

        $this->assertFileExists($this->stateFile('week'));
        $this->assertStringContainsString('/private/', $this->stateFile('week'));
    }
}
