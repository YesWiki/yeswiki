<?php

namespace YesWiki\Kernel\Entity;

/** What one housekeeping sweep did, step by step, and which steps threw (ticket 54). */
final class MaintenanceReport
{
    /** @var array<string, string> step => what it did, in the operator's language */
    private array $steps = [];

    /** @var array<string, string> step => why it did not */
    private array $failures = [];

    private float $duration = 0.0;

    public function did(string $step, string $outcome): void
    {
        $this->steps[$step] = $outcome;
    }

    public function threw(string $step, \Throwable $failure): void
    {
        $this->failures[$step] = $failure->getMessage() === ''
            ? $failure::class
            : $failure::class . ': ' . $failure->getMessage();
    }

    /** @return array<string, string> */
    public function steps(): array
    {
        return $this->steps;
    }

    /** @return array<string, string> */
    public function failures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function took(float $seconds): void
    {
        $this->duration = $seconds;
    }

    public function duration(): float
    {
        return $this->duration;
    }
}
