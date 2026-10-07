<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Federation\Service\SeenSignatures;

/** Moves seen signatures from their table to triples. */
class SeenSignaturesMoveToTheTriples extends YesWikiMigration
{
    public const TABLE = 'activitypub_seen_signatures';

    public function run()
    {
        $table = trim($this->dbService->prefixTable(self::TABLE));
        if (!in_array($table, $this->dbService->schema()->getTables(), true)) {
            return;
        }
        $seenSignatures = $this->getService(SeenSignatures::class);
        $quoted = $this->dbService->quoteIdentifier($table);
        $rows = $this->dbService->loadAll("SELECT hash, seen_at FROM {$quoted}");
        foreach ($rows as $row) {
            $seenSignatures->import((string)$row['hash'], (int)$row['seen_at']);
        }
        $this->dbService->query("DROP TABLE IF EXISTS {$quoted}");
        if (count($rows) > 0) {
            $this->say(count($rows) . ' seen ActivityPub signature(s) moved into triples.');
        }
    }
}
