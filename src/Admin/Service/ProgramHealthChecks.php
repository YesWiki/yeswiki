<?php

namespace YesWiki\Admin\Service;

use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Health\HealthCheck;
use YesWiki\Kernel\Health\ProvidesHealthChecks;

/** The silent upgrade failure: extensions left behind in `tools/`, which nothing scans any more (ticket 53). */
class ProgramHealthChecks implements ProvidesHealthChecks
{
    private LocalFiles $localFiles;

    public function __construct(LocalFiles $localFiles)
    {
        $this->localFiles = $localFiles;
    }

    public function healthChecks(): array
    {
        return [
            HealthCheck::named('leftover-tools-directory')
                ->label(_t('HEALTH_LEFTOVER_TOOLS'))
                ->says(_t('HEALTH_LEFTOVER_TOOLS_SAYS'))
                ->runs(function (): ?string {
                    if (!$this->localFiles->isDirectory('tools')) {
                        return null;
                    }

                    $entries = array_values(array_filter(
                        $this->localFiles->entriesIn('tools'),
                        fn (string $entry): bool => $this->localFiles->isDirectory('tools/' . $entry)
                    ));

                    return $entries === [] ? null : implode(', ', $entries);
                }),
        ];
    }
}
