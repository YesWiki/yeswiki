<?php

namespace YesWiki\Files\Service;

use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/** What Storage knows about itself: whether there is room, and whether the bucket answers (ticket 52). */
class StorageHealthChecks implements ProvidesHealthChecks
{
    /**
     * Under this much free space in the Runtime tier, a wiki is one archive away from failing to
     * write anything: SQLite, the search index and the container cache all live there.
     */
    public const RUNTIME_FLOOR_BYTES = 100 * 1024 * 1024;

    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('runtime-free-space')
                ->label(_t('HEALTH_FREE_SPACE'))
                ->says(_t('HEALTH_FREE_SPACE_SAYS'))
                ->runs(function (): ?string {
                    $free = $this->storage->runtimeFreeSpace();
                    if ($free === null || $free >= self::RUNTIME_FLOOR_BYTES) {
                        return null;
                    }

                    return _t('HEALTH_FREE_SPACE_FAILED', ['left' => $this->megabytes($free), 'floor' => $this->megabytes(self::RUNTIME_FLOOR_BYTES)]);
                }),

            HealthCheck::named('bucket-reachable')
                ->label(_t('HEALTH_BUCKET'))
                ->says(_t('HEALTH_BUCKET_SAYS'))
                ->actionableWhen(fn (): bool => $this->storage->remoteTiers() !== [])
                ->runs(function (): ?string {
                    return $this->storage->remoteReachable() === false
                        ? _t('HEALTH_BUCKET_FAILED', ['tiers' => implode(', ', $this->storage->remoteTiers())])
                        : null;
                }),
        ];
    }

    private function megabytes(float $bytes): string
    {
        return number_format($bytes / (1024 * 1024), 0) . ' MB';
    }
}
