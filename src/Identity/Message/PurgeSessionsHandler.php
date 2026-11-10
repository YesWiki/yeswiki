<?php

namespace YesWiki\Identity\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\SessionSchema;

/** Deletes the session rows whose lifetime is over, as PdoSessionHandler's own collection would. */
#[AsMessageHandler]
final class PurgeSessionsHandler
{
    public function __construct(
        private readonly DbService $db,
        private readonly SessionSchema $schema,
    ) {
    }

    public function __invoke(PurgeSessions $task): void
    {
        if ($this->schema->exists()) {
            $this->db->query("DELETE FROM {$this->schema->table()} WHERE sess_lifetime < ?", [time()]);
        }
    }
}
