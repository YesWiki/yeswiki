<?php

use YesWiki\Core\YesWikiMigration;

/** The `acls` table gives way to `pages.metadata.acls`, carrying every page's lists across before it is dropped. */
class DropAclsTable extends YesWikiMigration
{
    public function run()
    {
        $acls = trim($this->dbService->prefixTable('acls'));
        if (in_array($acls, $this->dbService->schema()->getTables(), true)) {
            $carried = $this->carryIntoMetadata($acls);
            if ($carried > 0) {
                $this->say("the access lists of {$carried} page(s) moved from the acls table into their metadata, in every revision");
            }
        }

        $this->dbService->query("DROP TABLE IF EXISTS {$acls}");
    }

    /** Writes each tag's read, write and comment lists into the metadata of all its revisions, keeping any other key already there. */
    public function carryIntoMetadata(string $acls): int
    {
        $pages = trim($this->dbService->prefixTable('pages'));
        $byTag = [];
        foreach ($this->dbService->loadAll("SELECT page_tag, privilege, list FROM {$acls}") as $row) {
            $list = (string)$row['list'];
            if ($list !== '') {
                $byTag[(string)$row['page_tag']][(string)$row['privilege']] = $list;
            }
        }

        return $this->dbService->transactional(fn (): int => $this->writeLists($pages, $byTag));
    }

    /**
     * @param array<string, array<string, string>> $byTag
     */
    private function writeLists(string $pages, array $byTag): int
    {
        $carried = 0;
        foreach ($byTag as $tag => $lists) {
            $revisions = $this->dbService->loadAll("SELECT id, metadata FROM {$pages} WHERE tag = ?", [$tag]);
            foreach ($revisions as $revision) {
                $metadata = json_decode((string)($revision['metadata'] ?? ''), true);
                $metadata = is_array($metadata) ? $metadata : [];
                $metadata['acls'] = $lists + ($metadata['acls'] ?? []);
                $this->dbService->query(
                    "UPDATE {$pages} SET metadata = ? WHERE id = ?",
                    [json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string)$revision['id']]
                );
            }
            if ($revisions !== []) {
                $carried++;
            }
        }

        return $carried;
    }
}
