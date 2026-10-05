<?php

use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\LegacyAttachments;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Search\Service\SearchIndexer;

/**
 * Repairs wikis that ran the first MigrateAttachmentsToPages: references it missed, uploads it left in files/, Bazar field files it moved away.
 */
class AttachedFileReferencesFindTheirFiles extends YesWikiMigration
{
    private const LISTED = 20;

    public function run()
    {
        $legacy = $this->getService(LegacyAttachments::class);
        $uploadPath = $this->uploadPath();

        $restored = $legacy->restoreEntryFieldFiles($uploadPath, $this->getService(AttachedFilePaths::class)->isSafeMode());
        $report = $legacy->migrateUploads($uploadPath);
        $rewrite = $legacy->rewriteReferences();
        if ($rewrite['rewritten'] !== []) {
            $this->getService(SearchIndexer::class)->enqueue($rewrite['rewritten']);
        }

        if ($restored !== []) {
            $this->say(count($restored) . ' Bazar field file(s) the first attachment migration had moved were copied back to files/, where entries read them: ' . self::listed($restored));
        }
        if ($report['created'] !== []) {
            $this->say(count($report['created']) . ' attached file(s) still in files/ became File Content in ' . FileManager::STORAGE_DIR . '/.');
        }
        if ($rewrite['rewritten'] !== []) {
            $this->say('file="…" references now name their File Content by tag in: ' . self::listed($rewrite['rewritten']));
        }
        foreach (['superseded' => 'older upload(s) left in files/', 'orphans' => 'file(s) left in files/ whose page no longer exists'] as $key => $sentence) {
            if ($report[$key] !== []) {
                $this->say(count($report[$key]) . " {$sentence}: " . self::listed($report[$key]));
            }
        }
        if ($rewrite['unresolved'] !== []) {
            $this->say(count($rewrite['unresolved']) . ' file="…" reference(s) still match no File Content of their page: ' . self::listed($rewrite['unresolved']));
        }
        $duplicates = $this->olderDuplicates($legacy);
        if ($duplicates !== []) {
            $this->say(count($duplicates) . ' File Content made from an older upload of a name a newer one replaced; nothing names them, they were kept: ' . self::listed($duplicates));
        }
    }

    private function uploadPath(): string
    {
        $attachConfig = $this->params->get('attach_config');
        $uploadPath = is_array($attachConfig) ? ($attachConfig['upload_path'] ?? '') : '';

        return is_string($uploadPath) && $uploadPath !== '' ? rtrim($uploadPath, '/') : 'files';
    }

    /** @return list<string> File Content tags superseded by a newer File Content of the same page and name, and named by no latest body */
    private function olderDuplicates(LegacyAttachments $legacy): array
    {
        $older = [];
        foreach ($legacy->fileIndex()['byOwner'] as $files) {
            $byName = [];
            foreach ($files as $file) {
                $byName[$file['name']][] = $file;
            }
            foreach ($byName as $same) {
                usort($same, static fn (array $a, array $b): int => $b['order'] <=> $a['order']);
                foreach (array_slice($same, 1) as $file) {
                    $older[] = $file['tag'];
                }
            }
        }
        if ($older === []) {
            return [];
        }

        $pages = $this->dbService->prefixTable('pages');
        $asText = $this->dbService->jsonAsText('body');

        return array_values(array_filter($older, fn (string $tag): bool => !$this->dbService->loadSingle(
            "SELECT 1 FROM {$pages} WHERE latest = 'Y' AND {$asText} LIKE ? LIMIT 1",
            ['%file=%' . $tag . '%']
        )));
    }

    /** @param list<string> $items */
    private static function listed(array $items): string
    {
        return implode(', ', array_slice($items, 0, self::LISTED)) . (count($items) > self::LISTED ? ', …' : '');
    }
}
