<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Content\Service\Trash;
use YesWiki\Kernel\Service\RuntimeConfig;

/** Erases the trash items older than `trash_keep_days` (30 by default, 0 keeps them); idempotent, so the default retry applies. */
#[AsMessageHandler]
final class PurgeTrashHandler
{
    public function __construct(
        private readonly RuntimeConfig $config,
        private readonly Trash $trash,
    ) {
    }

    public function __invoke(PurgeTrash $task): void
    {
        $this->trash->purgeOlderThan((int)$this->config->getValue('trash_keep_days', Trash::DEFAULT_KEEP_DAYS));
    }
}
