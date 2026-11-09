<?php

namespace YesWiki\Import\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Import this data source": the recurring task of one source flagged `syncOnMaintenance` in the `dataSources` configuration.
 * It carries only the source's key; its options are read when the message is handled. A key too long for a task id is hashed there.
 */
final class SyncDataSource implements ScheduledTask
{
    public function __construct(
        public readonly string $sourceId,
    ) {
    }

    public function taskId(): string
    {
        $id = 'sync-' . $this->sourceId;

        return strlen($id) <= ScheduledTask::MAX_ID_LENGTH ? $id : 'sync-' . sha1($this->sourceId);
    }

    public function label(): string
    {
        return _t('IMPORTER_TASK_SYNC', ['source' => $this->sourceId]);
    }
}
