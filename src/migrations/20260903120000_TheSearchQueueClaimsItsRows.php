<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexSchema;

/** Ticket 54: a drain takes the rows it is about to work on, so two of them cannot hold the same tags. */
class TheSearchQueueClaimsItsRows extends YesWikiMigration
{
    public function run()
    {
        $db = $this->getService(DbService::class);
        $queue = $this->getService(SearchIndexSchema::class)->queueTable();

        if (!in_array($queue, $db->schema()->getTables(), true)) {
            return;
        }

        $timestamp = $db->getDriver() === 'sqlite' ? 'TEXT' : 'TIMESTAMP';
        $text = $db->getDriver() === 'sqlite' ? 'TEXT' : 'VARCHAR(64)';

        $this->addColumn($db, $queue, 'claimed_at', $timestamp . ' NULL DEFAULT NULL');
        $this->addColumn($db, $queue, 'claimed_by', $text . ' NULL DEFAULT NULL');

        try {
            $db->query("CREATE INDEX {$db->quoteIdentifier($queue . '_idx_claimed_by')} ON {$queue} (claimed_by)");
        } catch (Throwable $alreadyThere) {
        }
    }

    private function addColumn(DbService $db, string $queue, string $column, string $definition): void
    {
        if ($db->schema()->columnExists(SearchIndexSchema::QUEUE_TABLE, $column)) {
            return;
        }
        $db->query("ALTER TABLE {$queue} ADD COLUMN {$db->quoteIdentifier($column)} {$definition}");
    }
}
