<?php

namespace YesWiki\Import\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/**
 * "Import this data source": the recurring task of one source flagged `syncOnMaintenance` in the `dataSources` configuration (ImportSchedule declares one per source).
 *
 * It carries the source's key and nothing of its options, which are read from the configuration
 * when the message is handled: a source edited or removed while the message waited is imported
 * as it is then, or not at all. A sync is idempotent (it updates what the source already
 * brought), so the default retry applies.
 */
final class SyncDataSource implements ScheduledTask
{
    public function __construct(
        public readonly string $sourceId,
    ) {
    }

    public function taskId(): string
    {
        return 'sync-' . $this->sourceId;
    }

    public function label(): string
    {
        return _t('IMPORTER_TASK_SYNC', ['source' => $this->sourceId]);
    }
}
