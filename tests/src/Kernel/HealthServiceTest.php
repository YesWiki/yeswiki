<?php

namespace YesWiki\Test\Kernel;

use PHPUnit\Framework\TestCase;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Health\Severity;
use YesWiki\Kernel\Service\HealthService;

/** Ticket 52 / ADR-0026: what raises a badge, what only waits on the screen, and what is never asked. */
class HealthServiceTest extends TestCase
{
    /**
     * @param list<HealthCheck> $checks
     */
    private function serviceWith(array $checks): HealthService
    {
        return new HealthService([new class($checks) implements ProvidesHealthChecks {
            /** @param list<HealthCheck> $checks */
            public function __construct(private readonly array $checks)
            {
            }

            public function healthChecks(): array
            {
                return $this->checks;
            }
        }]);
    }

    public function testAWikiWithNothingWrongShowsNothingAndRaisesNoBadge(): void
    {
        $service = $this->serviceWith([
            HealthCheck::named('fine')->runs(static fn () => null),
            HealthCheck::named('also-fine')->degraded()->runs(static fn () => null),
        ]);

        $this->assertSame([], $service->findings());
        $this->assertSame(0, $service->brokenCount());
    }

    public function testOnlyBrokenRaisesTheBadge(): void
    {
        $service = $this->serviceWith([
            HealthCheck::named('missing-ext-intl')->degraded()->runs(static fn () => 'not loaded'),
            HealthCheck::named('missing-ext-gd')->runs(static fn () => 'not loaded'),
        ]);

        $this->assertSame(1, $service->brokenCount(), 'degraded waits on the screen with its explanation');
        $this->assertCount(2, $service->findings(), 'and is still on the screen');
    }

    public function testBrokenComesFirst(): void
    {
        $service = $this->serviceWith([
            HealthCheck::named('degraded')->degraded()->runs(static fn () => 'a'),
            HealthCheck::named('broken')->runs(static fn () => 'b'),
        ]);

        $findings = $service->findings();
        $this->assertSame('broken', $findings[0]->check->id());
        $this->assertTrue($findings[0]->isBroken());
        $this->assertSame(Severity::Degraded, $findings[1]->check->severity());
    }

    /**
     * A badge a webmaster cannot clear is permanent, and a permanent badge teaches people to
     * ignore every badge -- so a check nobody here can act on is not run at all.
     */
    public function testWhatCannotBeFixedFromHereIsNeverReported(): void
    {
        $log = new class {
            public int $times = 0;
        };
        $service = $this->serviceWith([
            HealthCheck::named('core-update')
                ->actionableWhen(static fn (): bool => false)
                ->runs(static function () use ($log) {
                    $log->times++;

                    return 'an update is available';
                }),
        ]);

        $this->assertSame([], $service->findings());
        $this->assertSame(0, $log->times, 'and it is not even asked, so it costs nothing either');
    }

    /** The badge asks about Broken only, so an update check's round trip is not on every page view. */
    public function testTheBadgeDoesNotRunTheDegradedChecks(): void
    {
        $log = new class {
            public int $times = 0;
        };
        $service = $this->serviceWith([
            HealthCheck::named('package-updates')->degraded()->runs(static function () use ($log) {
                $log->times++;

                return null;
            }),
        ]);

        $service->brokenCount();
        $this->assertSame(0, $log->times);

        $service->findings();
        $this->assertSame(1, $log->times, 'the screen asks everything');
    }

    /** A check that cannot answer is a finding, not a stack trace on an admin screen. */
    public function testACheckThatThrowsBecomesTheFindingItCouldNotMake(): void
    {
        $service = $this->serviceWith([
            HealthCheck::named('bucket')->runs(static fn () => throw new \RuntimeException('no route to host')),
        ]);

        $findings = $service->findings();
        $this->assertCount(1, $findings);
        $this->assertSame('no route to host', $findings[0]->detail);
    }

    /** What a migration asks for, so a finding is run where the operator is standing (ticket 53). */
    public function testOneCheckCanBeRunByName(): void
    {
        $service = $this->serviceWith([
            HealthCheck::named('leftover-tools-directory')->runs(static fn () => 'bazar, calendrier'),
            HealthCheck::named('other')->runs(static fn () => 'not this one'),
        ]);

        $this->assertSame('bazar, calendrier', $service->run('leftover-tools-directory')?->detail);
        $this->assertNull($service->run('nothing-declares-this'));
    }

    public function testAFindingForgetsItselfAsSoonAsTheCheckPasses(): void
    {
        $themes = new class {
            public bool $stillCallThem = true;
        };
        $service = $this->serviceWith([
            HealthCheck::named('themes')->runs(static function () use ($themes) {
                return $themes->stillCallThem ? 'themes/foo.twig' : null;
            }),
        ]);

        $this->assertCount(1, $service->findings());

        $themes->stillCallThem = false;
        $service->startNewRequest();

        $this->assertSame([], $service->findings(), 'no acknowledgement, no state, no second run');
    }
}
