<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;

/**
 * Attached files are their own Content type (ticket 17, formerly tools/attach) -- a
 * `pages` row per uploaded file, tagged via TripleStore::TYPE_URI the same way
 * FormManager/UserManager tag forms/users, with its own independent ACL. A file is
 * uploaded once but can be referenced/embedded from any number of pages via
 * {{attach file="fileTag"}} -- there is no single "owning page" after creation, only
 * at creation time (used to seed the initial read ACL from whichever page the upload
 * happened on).
 */
class FileManager
{
    public const TRIPLES_FILE_TYPE = 'file';

    // private/ is denied by both the shipped nginx.conf and private/.htaccess, unlike the
    // web-root files/ directory static assets used to live in -- direct web access to a
    // file's bytes must be impossible, or the ACL enforced by the download API route below
    // is meaningless (anyone who guesses/knows the filename could just fetch it by URL).
    public const STORAGE_DIR = 'private/files';

    protected $wiki;
    protected $tripleStore;
    protected $pageManager;
    protected $aclService;

    private array $fileTagCache = [];

    public function __construct(
        Wiki $wiki,
        TripleStore $tripleStore,
        PageManager $pageManager,
        AclService $aclService
    ) {
        $this->wiki = $wiki;
        $this->tripleStore = $tripleStore;
        $this->pageManager = $pageManager;
        $this->aclService = $aclService;
    }

    public function isFileTag(string $tag): bool
    {
        if (empty($tag)) {
            return false;
        }
        if (!isset($this->fileTagCache[$tag])) {
            $this->fileTagCache[$tag] = !is_null($this->tripleStore->exist($tag, TripleStore::TYPE_URI, self::TRIPLES_FILE_TYPE, '', ''));
        }

        return $this->fileTagCache[$tag];
    }

    public function getAllFileTags(): array
    {
        return array_values(array_filter(array_map(function ($triple) {
            return $triple['resource'] ?? null;
        }, $this->tripleStore->getMatching(null, TripleStore::TYPE_URI, self::TRIPLES_FILE_TYPE))));
    }

    public function getOne(string $tag): ?array
    {
        if (!$this->isFileTag($tag)) {
            return null;
        }
        $page = $this->pageManager->getOne($tag, null, true, true);
        if (empty($page)) {
            return null;
        }

        return array_merge(['tag' => $tag], $this->pageManager->getMetadata($tag) ?? []);
    }

    public function getPhysicalPath(string $tag): ?string
    {
        $entry = $this->getOne($tag);
        if (empty($entry['stored_filename'])) {
            return null;
        }
        $path = self::STORAGE_DIR . '/' . $entry['stored_filename'];

        return file_exists($path) ? $path : null;
    }

    /**
     * Register an already-uploaded/moved physical file as a new file-entry, seeding its
     * ACL from $ownerPageTag's current read ACL. Does NOT move bytes onto disk itself --
     * callers (the upload API route, the attachments migration) are responsible for that,
     * this only creates the Content-type record + storage bookkeeping.
     */
    public function create(string $originalFilename, string $storedFilename, string $ownerPageTag, int $size, string $mimeType): array
    {
        $tag = $this->pageManager->suggestFreeTag($this->slugForTag($originalFilename));

        $saved = $this->pageManager->save($tag, '', '', true);
        if ($saved !== 0) {
            throw new \Exception("Could not save new file entry for '$originalFilename'.");
        }

        $this->tripleStore->create($tag, TripleStore::TYPE_URI, self::TRIPLES_FILE_TYPE, '', '');
        $this->fileTagCache[$tag] = true;

        $this->pageManager->setMetadata($tag, [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'size' => $size,
            'mime_type' => $mimeType,
            'uploaded_from' => $ownerPageTag,
        ]);

        $readAcl = $this->aclService->load($ownerPageTag, 'read');
        if (!empty($readAcl['list'])) {
            $this->aclService->save($tag, 'read', $readAcl['list']);
        }

        return $this->getOne($tag);
    }

    /**
     * Resolve a collision-free physical filename under files/, matching
     * PageManager::suggestFreeTag()'s try-then-suffix pattern but for a filename.
     */
    public function suggestFreeFilename(string $sanitizedFilename): string
    {
        if (!is_dir(self::STORAGE_DIR)) {
            mkdir(self::STORAGE_DIR, 0755, true);
        }
        if (!file_exists(self::STORAGE_DIR . '/' . $sanitizedFilename)) {
            return $sanitizedFilename;
        }

        $pathinfo = pathinfo($sanitizedFilename);
        $base = $pathinfo['filename'];
        $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

        for ($suffix = 2; $suffix <= 1000; $suffix++) {
            $candidate = $base . '-' . $suffix . $ext;
            if (!file_exists(self::STORAGE_DIR . '/' . $candidate)) {
                return $candidate;
            }
        }

        throw new \Exception("Could not find a free filename for '$sanitizedFilename'.");
    }

    /**
     * Ported from tools/attach/libs/attach.lib.php's Attach::sanitizeFilename().
     */
    public function sanitizeFilename(string $filename): string
    {
        $search = ['@[éèêëÊË]@i', '@[àâäÂÄ]@i', '@[îïÎÏ]@i', '@[ûùüÛÜ]@i', '@[ôöÔÖ]@i', '@[ç]@i', '@[ ]@i', '@[^a-zA-Z0-9_\.\-]@'];
        $replace = ['e', 'a', 'i', 'u', 'o', 'c', '_', ''];

        return preg_replace($search, $replace, mb_convert_encoding($filename, 'ISO-8859-1', 'UTF-8'));
    }

    public function delete(string $tag): void
    {
        if (!$this->isFileTag($tag)) {
            return;
        }
        $entry = $this->getOne($tag);
        if (!empty($entry['stored_filename'])) {
            $path = self::STORAGE_DIR . '/' . $entry['stored_filename'];
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $this->pageManager->deleteOrphaned($tag);
        unset($this->fileTagCache[$tag]);
    }

    private function slugForTag(string $originalFilename): string
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $slug = \URLify::slug($base);

        return empty($slug) ? 'file' : $slug;
    }

    /**
     * Legacy tools/attach filenames were prefixed with their owning page's tag
     * ({pageTag}_{name}_{pageDate}_{uploadDate}.{ext}, see attach.lib.php's
     * GetFullFilename()) -- both the ticket 17 migration (converting existing
     * uploads into file entries) and the image-resize-cache API route (whose raw
     * $filename argument, sourced from Bazar's own image/file fields which upload
     * through this same legacy convention, was never migrated to a file-entry tag)
     * need to recover that owning page tag from an arbitrary such filename, to
     * enforce a real read-ACL check where none existed before. Page tags can
     * themselves contain underscores, so this tries progressively longer
     * underscore-joined prefixes and keeps the longest one that's an actual page.
     */
    public function guessOwnerPageTagFromLegacyFilename(string $filename): ?string
    {
        $segments = explode('_', pathinfo($filename, PATHINFO_FILENAME));
        $longestMatch = null;
        for ($i = 1; $i < count($segments); $i++) {
            $candidate = implode('_', array_slice($segments, 0, $i));
            if ($this->pageManager->tagExists($candidate)) {
                $longestMatch = $candidate;
            }
        }

        return $longestMatch;
    }
}
