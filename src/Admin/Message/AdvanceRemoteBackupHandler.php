<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Admin\Service\RemoteBackupService;

/**
 * Takes one step of the fetch from another wiki, and queues the next while there is one.
 * A step that fails has already given the fetch up: the message fails for good with its reason, for the jobs page.
 */
#[AsMessageHandler]
final class AdvanceRemoteBackupHandler
{
    public function __construct(
        private readonly RemoteBackupService $remoteBackups,
    ) {
    }

    public function __invoke(AdvanceRemoteBackup $step): void
    {
        $state = $this->remoteBackups->advance();
        if (isset($state['error'])) {
            throw new UnrecoverableMessageHandlingException((string)$state['error']);
        }
        if ($state['running'] && !$this->remoteBackups->queueNextStep()) {
            throw new UnrecoverableMessageHandlingException('The next step of the remote backup could not be queued: see the Journal.');
        }
    }
}
