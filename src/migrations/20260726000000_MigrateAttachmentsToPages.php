<?php

use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\FileManager;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 17: uploaded files become their own Content type (a `pages` row per file,
 * own ACL, see FileManager). This migration:
 *  1) scans the legacy upload folder (`attach_config[upload_path]`, historically
 *     `files/`) for existing physical uploads, both the flat safe_mode naming
 *     (`{pageTag}_{name}_{pageDate}_{uploadDate}.{ext}[_]`) and the no_safe_mode
 *     subdirectory naming (`{pageTag}/{name}_{pageDate}_{uploadDate}.{ext}[_]`),
 *     moves each into FileManager::STORAGE_DIR and registers it as a file entry
 *     (ACL seeded from the owning page's *current* read ACL);
 *  2) rewrites every page body's `file="oldRawFilename"` argument (across {{attach}}
 *     and its sibling actions) to the new file tag, site-wide.
 *
 * Files whose name doesn't match the known legacy convention are left in place --
 * some seed/demo assets (e.g. files/yeswiki-logo.png) were never real {{attach}}
 * uploads and aren't referenced via file="..." at all, so there's nothing to migrate.
 */
class MigrateAttachmentsToPages extends YesWikiMigration
{
    private const LEGACY_NAME_PATTERN = '`^(.*)_(\d{14})_(\d{14})\.([^._]+)_?$`';

    public function run()
    {
        $uploadPath = rtrim($this->getUploadPath(), '/');
        if (!is_dir($uploadPath)) {
            return;
        }

        $fileManager = $this->getService(FileManager::class);
        $pageManager = $this->getService(PageManager::class);
        $aclService = $this->getService(AclService::class);

        $renameMap = $this->migrateFiles($uploadPath, $fileManager, $pageManager, $aclService);
        if (!empty($renameMap)) {
            $this->rewritePageBodies($renameMap, $pageManager);
        }
    }

    private function getUploadPath(): string
    {
        $attachConfig = $this->params->get('attach_config');

        return !empty($attachConfig['upload_path']) ? $attachConfig['upload_path'] : 'files';
    }

    /**
     * @return array<string,string> old raw filename => new file tag
     */
    private function migrateFiles(string $uploadPath, FileManager $fileManager, PageManager $pageManager, AclService $aclService): array
    {
        $renameMap = [];

        foreach (scandir($uploadPath) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $uploadPath . '/' . $entry;

            if (is_dir($entryPath)) {
                // no_safe_mode subdirectory case: the directory name IS the owner page tag
                if (!$pageManager->tagExists($entry)) {
                    continue;
                }
                foreach (scandir($entryPath) as $subEntry) {
                    if ($subEntry === '.' || $subEntry === '..') {
                        continue;
                    }
                    $this->migrateOneFile($entryPath . '/' . $subEntry, $subEntry, $entry, $fileManager, $aclService, $renameMap);
                }
                continue;
            }

            // flat safe_mode case: recover the owner page tag from the filename prefix
            $ownerPageTag = $fileManager->guessOwnerPageTagFromLegacyFilename($entry);
            if (is_null($ownerPageTag)) {
                continue;
            }
            $this->migrateOneFile($entryPath, $entry, $ownerPageTag, $fileManager, $aclService, $renameMap, $ownerPageTag);
        }

        return $renameMap;
    }

    private function migrateOneFile(
        string $physicalPath,
        string $rawFilename,
        string $ownerPageTag,
        FileManager $fileManager,
        AclService $aclService,
        array &$renameMap,
        ?string $stripPrefix = null
    ): void {
        $originalFilename = self::recoverOriginalFilename($rawFilename, $stripPrefix);
        if (is_null($originalFilename)) {
            return;
        }

        $size = filesize($physicalPath);
        $mimeType = mime_content_type($physicalPath) ?: 'application/octet-stream';

        $storedFilename = $fileManager->suggestFreeFilename($fileManager->sanitizeFilename($originalFilename));
        if (!copy($physicalPath, FileManager::STORAGE_DIR . '/' . $storedFilename)) {
            return;
        }

        $entry = $fileManager->create($originalFilename, $storedFilename, $ownerPageTag, (int)$size, $mimeType);
        unlink($physicalPath);

        $renameMap[$rawFilename] = $entry['tag'];
    }

    /**
     * Strip the trailing `_{pageDate}_{uploadDate}.{ext}[_]` suffix (and, for the flat
     * safe_mode case, the leading `{pageTag}_` prefix) to recover the name the user
     * originally uploaded. Returns null if the filename doesn't match the legacy
     * convention at all (not a real {{attach}} upload -- left untouched).
     */
    public static function recoverOriginalFilename(string $rawFilename, ?string $stripPrefix): ?string
    {
        $matches = [];
        if (!preg_match(self::LEGACY_NAME_PATTERN, $rawFilename, $matches)) {
            return null;
        }
        $namePart = $matches[1];
        $ext = $matches[4];

        if (!is_null($stripPrefix) && strpos($namePart, $stripPrefix . '_') === 0) {
            $namePart = substr($namePart, strlen($stripPrefix) + 1);
        }
        if ($namePart === '') {
            return null;
        }

        return $namePart . '.' . $ext;
    }

    private function rewritePageBodies(array $renameMap, PageManager $pageManager): void
    {
        // longest-filename-first, so e.g. "photo.jpg" doesn't get rewritten inside a
        // page body that actually references "photo.jpg_" or a longer sibling name
        uksort($renameMap, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        $rows = $this->dbService->loadAll(
            "SELECT tag, body FROM {$this->dbService->prefixTable('pages')} WHERE latest = 'Y'"
        );
        foreach ($rows as $row) {
            if (empty($row['body']) || strpos($row['body'], 'file="') === false) {
                continue;
            }
            $newBody = $row['body'];
            foreach ($renameMap as $oldFilename => $newTag) {
                $newBody = str_replace('file="' . $oldFilename . '"', 'file="' . $newTag . '"', $newBody);
            }
            if ($newBody !== $row['body']) {
                $pageManager->save($row['tag'], $newBody, '', true);
            }
        }
    }
}
