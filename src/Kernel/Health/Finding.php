<?php

namespace YesWiki\Kernel\Health;

/** One failing check, as it was found this time round. */
final class Finding
{
    public function __construct(
        public readonly HealthCheck $check,
        public readonly string $detail,
    ) {
    }

    public function isBroken(): bool
    {
        return $this->check->severity() === Severity::Broken;
    }
}
