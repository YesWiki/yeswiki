<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/**
 * Move a file's own attributes out of `metadata` and into its `body`.
 *
 * ADR-0002's ticket-09 amendment states the rule -- `metadata` holds how Content is
 * presented and who may see it, `body` holds what the Content *is* -- and names file
 * attributes as one of the two groups that moved. FileManager was rewritten to read and
 * write them in the body accordingly, but nothing ever moved the rows the earlier
 * attachments migration had already created with them in `metadata`. The result was
 * silent and total: `getPhysicalPath()` looks for `body.stored_filename`, finds nothing,
 * and **every existing file 404s**.
 *
 * Applied to every revision, not just the current one, so a file's history reads the same
 * way its present does. `acls` stays in metadata -- that is presentation and access, and
 * is not part of the file entry.
 *
 * Idempotent: a revision whose body already carries the attributes is left alone, and an
 * attribute present in both wins from the body.
 */
class FileAttributesIntoBody extends YesWikiMigration
{
    /** The file's own data, as declared by ContentTypeSchema and written by FileManager. */
    private const ATTRIBUTES = ['original_filename', 'stored_filename', 'size', 'mime_type', 'uploaded_from'];

    public function run(): void
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));
        $triples = trim($dbService->prefixTable('triples'));

        $rows = $dbService->loadAll(
            "SELECT p.id, p.body, p.metadata FROM {$pages} p"
            . " WHERE EXISTS (SELECT 1 FROM {$triples} t WHERE t.resource = p.tag"
            . " AND t.property = '" . $dbService->escape(TripleStore::TYPE_URI) . "'"
            . " AND t.value = '" . $dbService->escape(FileManager::TRIPLES_FILE_TYPE) . "')"
        );

        foreach ($rows as $row) {
            $metadata = json_decode((string)($row['metadata'] ?? ''), true);
            if (!is_array($metadata)) {
                continue;
            }

            $moved = array_intersect_key($metadata, array_flip(self::ATTRIBUTES));
            if (empty($moved)) {
                continue;
            }

            // the body wins where both carry an attribute: it is the side FileManager
            // has been writing since the amendment
            $body = array_merge($moved, PageBody::decode($row['body'] ?? null));
            $metadata = array_diff_key($metadata, $moved);

            $dbService->query(
                "UPDATE {$pages} SET body = '" . $dbService->escape(PageBody::encode($body)) . "',"
                . " metadata = '" . $dbService->escape((string)json_encode($metadata, PageBody::JSON_FLAGS)) . "'"
                . " WHERE id = '" . $dbService->escape($row['id']) . "'"
            );
        }
    }
}
