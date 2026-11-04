<?php

namespace YesWiki\Identity\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Identity\Service\BotGuard;

/** Deletes the anti-bot tokens that have expired and the refusal counters past BotGuard::COUNTERS_KEPT_DAYS. */
#[AsMessageHandler]
final class PurgeBotGuardHandler
{
    public function __construct(
        private readonly BotGuard $botGuard,
    ) {
    }

    public function __invoke(PurgeBotGuard $task): void
    {
        $this->botGuard->purge();
    }
}
