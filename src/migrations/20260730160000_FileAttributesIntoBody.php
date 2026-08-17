<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FileManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\DbService;

/** Move a file's own attributes out of `metadata` and into its `body`. */
class FileAttributesIntoBody extends YesWikiMigration
{
    /** The file's own data, as declared by ContentTypeSchema and written by FileManager. */
    private const ATTRIBUTES = ['original_filename', 'stored_filename', 'size', 'mime_type', 'uploaded_from'];

    public function run(): void
    {
        $dbService = $this->getService(DbService::class);
        $pages = trim($dbService->prefixTable('pages'));

        $rows = $dbService->loadAll(
            "SELECT id, body, metadata FROM {$pages}"
            . ' WHERE ' . $dbService->quoteIdentifier('type')
            . " = '" . $dbService->escape(PageType::FILE) . "'"
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
