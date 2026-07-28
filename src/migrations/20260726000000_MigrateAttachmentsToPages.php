<?php

use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
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
 *  2) rewrites each owning page's own body, replacing `file="originalFilename"`
 *     (across {{attach}} and its sibling actions) with `file="newTag"`.
 *
 * Files whose name doesn't match the known legacy convention are left in place --
 * some seed/demo assets (e.g. files/yeswiki-logo.png) were never real {{attach}}
 * uploads and aren't referenced via file="..." at all, so there's nothing to migrate.
 *
 * The rewrite in step 2 is deliberately scoped to each file's own owning page, not a
 * single global filename => tag map applied to every page site-wide: `file="..."` in
 * a page body was never a globally unique identifier -- the pre-migration upload flow
 * (qq.lib.php's handleUpload()) never enforced uniqueness of the short "simplefilename"
 * across different pages, and GetFullFilename()'s search resolves a bare `file="name.ext"`
 * relative to the *current* page's tag. Two different pages that each uploaded a
 * same-named file (both "report.pdf", say) would collide into one entry in a global
 * map and silently mis-rewrite one page's reference to the other's file/ACL. Scoping
 * the replacement to each file's own owning page's body avoids that entirely.
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

        $renameMapByOwnerPage = $this->migrateFiles($uploadPath, $fileManager, $pageManager);
        if (!empty($renameMapByOwnerPage)) {
            $this->rewritePageBodies($renameMapByOwnerPage, $pageManager);
        }
    }

    private function getUploadPath(): string
    {
        $attachConfig = $this->params->get('attach_config');

        return !empty($attachConfig['upload_path']) ? $attachConfig['upload_path'] : 'files';
    }

    /**
     * @return array<string,array<string,string>> ownerPageTag => [originalFilename => newTag]
     */
    private function migrateFiles(string $uploadPath, FileManager $fileManager, PageManager $pageManager): array
    {
        $renameMapByOwnerPage = [];

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
                    $this->migrateOneFile($entryPath . '/' . $subEntry, $subEntry, $entry, $fileManager, $renameMapByOwnerPage);
                }
                continue;
            }

            // flat safe_mode case: recover the owner page tag from the filename prefix
            $ownerPageTag = $fileManager->guessOwnerPageTagFromLegacyFilename($entry);
            if (is_null($ownerPageTag)) {
                continue;
            }
            $this->migrateOneFile($entryPath, $entry, $ownerPageTag, $fileManager, $renameMapByOwnerPage, $ownerPageTag);
        }

        return $renameMapByOwnerPage;
    }

    private function migrateOneFile(
        string $physicalPath,
        string $rawFilename,
        string $ownerPageTag,
        FileManager $fileManager,
        array &$renameMapByOwnerPage,
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

        // page bodies reference the short original filename (`file="report.pdf"`), never
        // the raw on-disk name with its page-tag prefix and timestamp suffix -- that's
        // what rewritePageBodies() needs to search for
        $renameMapByOwnerPage[$ownerPageTag][$originalFilename] = $entry['tag'];
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

    /**
     * @param array<string,array<string,string>> $renameMapByOwnerPage ownerPageTag => [originalFilename => newTag]
     */
    private function rewritePageBodies(array $renameMapByOwnerPage, PageManager $pageManager): void
    {
        foreach ($renameMapByOwnerPage as $ownerPageTag => $renameMap) {
            $page = $pageManager->getOne($ownerPageTag, null, true, true);
            if (empty($page) || empty($page['body']) || strpos($page['body'], 'file="') === false) {
                continue;
            }

            // longest-filename-first, so e.g. "photo.jpg" doesn't get rewritten inside
            // a reference to "photo.jpg_" or a longer sibling name
            uksort($renameMap, function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            $newBody = $page['body'];
            foreach ($renameMap as $originalFilename => $newTag) {
                $newBody = str_replace('file="' . $originalFilename . '"', 'file="' . $newTag . '"', $newBody);
            }
            if ($newBody !== $page['body']) {
                $pageManager->save($ownerPageTag, $newBody, '', true);
            }
        }
    }
}
