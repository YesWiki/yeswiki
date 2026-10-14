<?php

namespace YesWiki\Admin\Service;

use YesWiki\Admin\Entity\PackageCore;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\RuntimeLock;
use YesWiki\Kernel\Entity\Messages;
use YesWiki\Kernel\Service\DoryphoreLeftovers;
use YesWiki\Kernel\Service\MigrationService;

/** Finishes what Doryphore's update screen leaves undone when it installs Ectoplasme: the default theme, then the migrations. */
class DoryphoreArrival
{
    public const LOCK = 'cache/doryphore-arrival.lock';

    public const IDLE = 'idle';
    public const BUSY = 'busy';
    public const AGAIN = 'again';
    public const DONE = 'done';

    public function __construct(
        private readonly AutoUpdateService $updates,
        private readonly MigrationService $migrations,
        private readonly RuntimeLock $locks,
        private readonly LocalFiles $files,
    ) {
    }

    /** Whether DoryphoreLeftovers set a Doryphore wiki aside and its move to Ectoplasme is not over yet. */
    public function isPending(string $instanceDir = YESWIKI_INSTANCE_DIR): bool
    {
        return $this->files->isFile($instanceDir . '/' . DoryphoreLeftovers::PENDING);
    }

    /** @return array{string, Messages} */
    public function finish(string $instanceDir = YESWIKI_INSTANCE_DIR, string $programDir = YESWIKI_PROGRAM_DIR): array
    {
        if (!$this->isPending($instanceDir)) {
            return [self::IDLE, new Messages()];
        }
        $handle = $this->locks->acquire(self::LOCK);
        if (!$this->locks->tryLock($handle)) {
            $this->locks->release($handle);

            return [self::BUSY, new Messages()];
        }
        try {
            if (!$this->stillPending($instanceDir)) {
                return [self::IDLE, new Messages()];
            }
            @set_time_limit(300);
            if (!$this->files->isDirectory($programDir . '/themes/' . THEME_PAR_DEFAUT)) {
                $messages = $this->reinstallCore($instanceDir, $programDir);

                return [$messages->hasError() ? self::DONE : self::AGAIN, $messages];
            }
            $messages = $this->migrations->run();
            if (!$messages->hasError()) {
                $this->files->remove($instanceDir . '/' . DoryphoreLeftovers::PENDING);
            }

            return [self::DONE, $messages];
        } finally {
            $this->locks->release($handle);
        }
    }

    /** The pending mark read again once the lock is held, since the request that held it before may have just removed it. */
    private function stillPending(string $instanceDir): bool
    {
        clearstatcache();

        return $this->isPending($instanceDir);
    }

    /** Ectoplasme's own updater copies the default theme that Doryphore's skips with the rest of themes/. */
    private function reinstallCore(string $instanceDir, string $programDir): Messages
    {
        $messages = new Messages();
        if (!$this->updates->mayUpgrade(PackageCore::CORE_NAME, $instanceDir, $programDir)) {
            return $messages->add('AU_DORYPHORE_THEME_FARM', 'AU_ERROR');
        }
        if (!$this->updates->initRepository()) {
            return $messages->add('AU_REPO_ERROR', 'AU_ERROR');
        }

        return $this->updates->upgrade(PackageCore::CORE_NAME);
    }
}
