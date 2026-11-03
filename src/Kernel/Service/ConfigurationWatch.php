<?php

namespace YesWiki\Kernel\Service;

/**
 * Whether the configuration and the code this process booted on are still the wiki's, for long-lived processes.
 * YesWikiRuntime binds the answer at boot; left unbound (a service built by hand, in a test), nothing ever changed.
 */
class ConfigurationWatch
{
    /** @var (\Closure(): bool)|null */
    private ?\Closure $changed = null;

    /** @param \Closure(): bool $changed */
    public function bind(\Closure $changed): void
    {
        $this->changed = $changed;
    }

    public function changedSinceBoot(): bool
    {
        return $this->changed !== null && ($this->changed)();
    }
}
