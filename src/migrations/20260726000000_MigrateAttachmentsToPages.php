<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;

/**
 * Ticket 17: uploaded files become their own Content type (a `pages` row per file, own ACL, see FileManager).
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

        $renameMapByOwnerPage[$ownerPageTag][$originalFilename] = $entry['tag'];
    }

    /**
     * Strip the trailing `_{pageDate}_{uploadDate}.{ext}[_]` suffix (and, for the flat safe_mode case, the leading `{pageTag}_` prefix) to recover the name the user originally uploaded.
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
            $markup = empty($page) ? '' : PageBody::content($page['body']);
            if ($markup === '' || strpos($markup, 'file="') === false) {
                continue;
            }

            uksort($renameMap, function ($a, $b) {
                return strlen($b) <=> strlen($a);
            });

            $newMarkup = $markup;
            foreach ($renameMap as $originalFilename => $newTag) {
                $newMarkup = str_replace('file="' . $originalFilename . '"', 'file="' . $newTag . '"', $newMarkup);
            }
            if ($newMarkup !== $markup) {
                $newBody = $page['body'];
                $newBody[PageBody::CONTENT] = $newMarkup;
                $pageManager->save($ownerPageTag, $newBody, '', true);
            }
        }
    }
}
