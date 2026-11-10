<?php

namespace YesWiki\Identity\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Identity\Service\AccountActivationService;

/** Clears the account activation keys past UserManager::KEY_TTL. */
#[AsMessageHandler]
final class PurgeActivationKeysHandler
{
    public function __construct(
        private readonly AccountActivationService $activation,
    ) {
    }

    public function __invoke(PurgeActivationKeys $task): void
    {
        $this->activation->purgeExpiredActivationKeys();
    }
}
