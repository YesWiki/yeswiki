<?php

namespace YesWiki\Kernel\Service;

/**
 * Whether the configuration and the code this process booted on are still the wiki's: what a process that outlives many requests asks before it goes on with what it was built from.
 *
 * The container, and every parameter a service holds, is what the configuration was when the
 * process booted, and its classes are the code that was there then. FrankenPHP's worker restarts
 * when either changed; a blocking queue consumer (`messenger:consume`) asks the same question
 * between two messages, since it would otherwise handle for up to an hour what was dispatched
 * under a configuration it never saw, or run old classes against the schema an upgrade migrated.
 *
 * The answer is the kernel's: YesWikiRuntime binds here at boot the two questions worker.php asks.
 * YesWikiRuntime::configurationChanged() compares the fingerprint of the container it booted on
 * (the config file, `private/.env`, the environment, the extensions) with the one a kernel would
 * build now; YesWikiRuntime::containerCacheIsGone() tells that the compiled container was emptied
 * (`migrate` after an upgrade, `cache:clear`), which the fingerprint does not see. The question
 * costs a few small file reads and hashes, so a caller in a loop spaces its calls. Left unbound (a
 * service built by hand, in a test), nothing ever changed.
 */
class ConfigurationWatch
{
    /** @var (\Closure(): bool)|null */
    private ?\Closure $changed = null;

    /** @param \Closure(): bool $changed whether what the container was built from has changed since this process booted */
    public function bind(\Closure $changed): void
    {
        $this->changed = $changed;
    }

    public function changedSinceBoot(): bool
    {
        return $this->changed !== null && ($this->changed)();
    }
}
