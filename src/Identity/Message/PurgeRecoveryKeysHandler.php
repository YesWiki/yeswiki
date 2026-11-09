<?php

namespace YesWiki\Identity\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Identity\Service\UserManager;

/** Deletes the password recovery keys past UserManager::KEY_TTL. */
#[AsMessageHandler]
final class PurgeRecoveryKeysHandler
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
    }

    public function __invoke(PurgeRecoveryKeys $task): void
    {
        $this->userManager->purgeExpiredPasswordRecoveryKeys();
    }
}
