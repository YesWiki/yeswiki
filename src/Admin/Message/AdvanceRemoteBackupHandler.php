<?php

namespace YesWiki\Admin\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use YesWiki\Admin\Service\RemoteBackupService;

/**
 * Takes one step of the fetch from another wiki, and queues the next while there is one.
 *
 * The next step is queued at once, or after RemoteWikiArchive::SETTLE_SECONDS while the remote
 * wiki writes its archive (see RemoteBackupService::queueNextStep()). On a wiki whose queue runs
 * at the end of page views, each step thus waits for a page view: the admin's own page polling
 * the fetch is one, any visitor's is another.
 *
 * A step that fails has already given the fetch up (RemoteBackupService::advance() tidies the
 * remote wiki and keeps the reason for the polling browser): the message fails for good with
 * that reason, for the jobs page. A message left from a fetch given up finds nothing to do.
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
