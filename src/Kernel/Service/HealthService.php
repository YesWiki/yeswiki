<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use YesWiki\Kernel\Health\Finding;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;
use YesWiki\Kernel\Health\Severity;

/**
 * Runs every check a module declared, and answers what is wrong right now (ticket 52 / ADR-0026).
 *
 * Nothing is stored. A finding is a claim about the present, so the screen is never out of date
 * and fixing something makes it disappear with no acknowledgement, no state and no second run.
 */
class HealthService implements RequestScopedState
{
    /** @var iterable<ProvidesHealthChecks> */
    private iterable $providers;

    /**
     * Derived once per request, per severity asked for: "is ext-intl loaded" costs nothing, and
     * "is there an update" costs a round trip nobody wants on an ordinary page view.
     *
     * @var array<string, list<Finding>>
     */
    private array $findings = [];

    /**
     * What each check said this request, by id: the badge's list and a screen's share one run of it.
     *
     * @var array<string, ?string>
     */
    private array $failures = [];

    /**
     * @param iterable<ProvidesHealthChecks> $providers tagged services, in no particular order
     */
    public function __construct(#[AutowireIterator('yeswiki.health_check_provider')] iterable $providers)
    {
        $this->providers = $providers;
    }

    public function startNewRequest(): void
    {
        $this->findings = [];
        $this->failures = [];
    }

    /**
     * What is failing, broken first.
     *
     * @param Severity|null $only  run only the checks of this severity -- which is how the badge
     *                             asks about Broken without paying for the rest
     * @param string|null   $route run only the checks that send the admin to that screen -- which is
     *                             how a screen lists its own alerts without paying for the others
     *
     * @return list<Finding>
     */
    public function findings(?Severity $only = null, ?string $route = null): array
    {
        $key = ($only === null ? 'all' : $only->value) . '@' . ($route ?? '');
        if (isset($this->findings[$key])) {
            return $this->findings[$key];
        }

        $findings = [];
        foreach ($this->checks() as $check) {
            if ($only !== null && $check->severity() !== $only) {
                continue;
            }

            if ($route !== null && $check->route() !== $route) {
                continue;
            }

            if (!$check->isActionable()) {
                continue;
            }

            $failure = $this->failureOf($check);
            if ($failure !== null) {
                $findings[] = new Finding($check, $failure);
            }
        }

        usort($findings, static fn (Finding $a, Finding $b): int => $b->isBroken() <=> $a->isBroken());

        return $this->findings[$key] = $findings;
    }

    /**
     * One named check, run now -- what a migration asks for the finding it used to write into a wiki page (ticket 53).
     */
    public function run(string $id): ?Finding
    {
        foreach ($this->checks() as $check) {
            if ($check->id() !== $id || !$check->isActionable()) {
                continue;
            }

            $failure = $check->failure();

            return $failure === null ? null : new Finding($check, $failure);
        }

        return null;
    }

    /** What the badge shows, and the only thing it runs: what is both broken and actionable. */
    public function brokenCount(): int
    {
        return count($this->findings(Severity::Broken));
    }

    /**
     * Every check this wiki knows how to run, passing or not.
     *
     * What a migration's `reportCheck()` names has to be one of these, and a name that is not is
     * a migration quietly reporting nothing (ticket 53).
     *
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (HealthCheck $check): string => $check->id(), $this->checks());
    }

    /** What that check says, asked once per request whichever list asks for it. */
    private function failureOf(HealthCheck $check): ?string
    {
        if (!array_key_exists($check->id(), $this->failures)) {
            try {
                $this->failures[$check->id()] = $check->failure();
            } catch (\Throwable $unanswerable) {
                $this->failures[$check->id()] = $unanswerable->getMessage();
            }
        }

        return $this->failures[$check->id()];
    }

    /**
     * @return list<HealthCheck>
     */
    private function checks(): array
    {
        $checks = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->healthChecks() as $check) {
                $checks[] = $check;
            }
        }

        return $checks;
    }
}
