<?php

namespace YesWiki\Kernel\Service;

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
     * @param iterable<ProvidesHealthChecks> $providers tagged services, in no particular order
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    public function startNewRequest(): void
    {
        $this->findings = [];
    }

    /**
     * What is failing, broken first.
     *
     * @param Severity|null $only run only the checks of this severity -- which is how the badge
     *                            asks about Broken without paying for the rest
     *
     * @return list<Finding>
     */
    public function findings(?Severity $only = null): array
    {
        $key = $only === null ? 'all' : $only->value;
        if (isset($this->findings[$key])) {
            return $this->findings[$key];
        }

        $findings = [];
        foreach ($this->checks() as $check) {
            if ($only !== null && $check->severity() !== $only) {
                continue;
            }

            // Never nag about what cannot be fixed from here: a permanent badge teaches people to
            // ignore every badge, including the one that matters (ADR-0007, ADR-0026).
            if (!$check->isActionable()) {
                continue;
            }

            try {
                $failure = $check->failure();
            } catch (\Throwable $unanswerable) {
                $failure = $unanswerable->getMessage();
            }

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
