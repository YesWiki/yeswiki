<?php

namespace YesWiki\Kernel\Service\Lock;

/** Exclusive locks a wiki takes against itself, named, held until released or until the process ends. */
interface Locks
{
    /** Takes the lock without waiting; $heldElsewhere says whether a refusal came from another holder rather than from a lock that cannot be taken at all. */
    public function tryAcquire(string $name, bool &$heldElsewhere = false): bool;

    /** Lets go of a lock this instance holds; nothing happens for one it does not. */
    public function release(string $name): void;

    /** True when some holder has the lock, false when it is free, null when nothing can tell. */
    public function probe(string $name): ?bool;

    /** Removes whatever a released or dead holder left behind of the lock. */
    public function forget(string $name): void;
}
