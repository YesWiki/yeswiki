<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\RuntimeConfig;

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

    protected ContainerInterface $container;
    protected $tripleStore;
    protected $pageManager;
    protected $aclService;

    private array $fileTagCache = [];

    public function __construct(
        ContainerInterface $container,
        TripleStore $tripleStore,
        PageManager $pageManager,
        AclService $aclService
    ) {
        $this->container = $container;
        $this->tripleStore = $tripleStore;
        $this->pageManager = $pageManager;
        $this->aclService = $aclService;
    }

    /**
     * "2M"/"512k"-style size string to bytes (historic Wiki::parse_size()).
     * Static: YesWikiKernel::build() needs it while the container is still compiling.
     */
    public static function parseSize(mixed $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', (string)$size); // Remove the non-unit characters from the size.
        $size = preg_replace('/[^0-9\.]/', '', (string)$size); // Remove the non-numeric characters from the size.
        if ($unit) {
            // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
            return intval(round((int)$size * pow(1024, (int)stripos('bkmgtpezy', $unit[0]))));
        }

        return intval(round((int)$size));
    }

    /**
     * Upload size limit in bytes: the strictest of PHP's upload_max_filesize and
     * post_max_size and the wiki's max_file_size config (historic Wiki::file_upload_max_size()).
     */
    public function uploadMaxSize(): int
    {
        return self::uploadMaxSizeFromConfig($this->container->get(RuntimeConfig::class)->getValue('max_file_size'));
    }

    /**
     * Same, from a raw max_file_size config value — for YesWikiKernel::build(), which
     * runs before any service can be constructed.
     */
    public static function uploadMaxSizeFromConfig(mixed $maxFileSizeConfig): int
    {
        $confMaxFileSize = !empty($maxFileSizeConfig) && !is_array($maxFileSizeConfig)
            ? self::parseSize(trim((string)$maxFileSizeConfig))
            : 0;

        $postMaxSize = self::parseSize(ini_get('post_max_size'));

        $uploadMax = self::parseSize(ini_get('upload_max_filesize'));

        // return the min size limit, excluding 0 values that mean no limit
        return min(array_filter([$confMaxFileSize, $postMaxSize, $uploadMax]) ?: [DEFAULT_MAX_UPLOAD_SIZE]);
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

        // a file's attributes are body fields (ADR-0002's ticket-09 amendment); metadata
        // still holds this Content's ACLs, which are not part of the file entry
        return array_merge(['tag' => $tag], $page['body'] ?? []);
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
     * Validate an upload and put its bytes on disk, returning the attributes a file
     * Content is made of. The one place uploads are vetted: the extension allow-list, the
     * size cap, the filename sanitising, and the SVG/XML purge that stops an uploaded
     * drawing from being an uploaded script. Two callers now enter here -- the upload API
     * route and the file-content form field -- and a second copy of these checks is a
     * second chance to forget one.
     *
     * @return array{original_filename: string, stored_filename: string, size: int, mime_type: string}
     *
     * @throws \InvalidArgumentException when the upload is missing or refused
     */
    public function storeUpload(UploadedFile $uploadedFile): array
    {
        if (!$uploadedFile->isValid()) {
            throw new \InvalidArgumentException(_t('ERROR_NO_FILE_UPLOADED'));
        }

        $originalFilename = $uploadedFile->getClientOriginalName();
        $ext = strtolower($uploadedFile->getClientOriginalExtension());
        $authorizedExtensions = $this->container->get(RuntimeConfig::class)['authorized-extensions'] ?? [];
        if (!empty($authorizedExtensions) && !array_key_exists($ext, $authorizedExtensions)) {
            throw new \InvalidArgumentException(_t('ERROR_NOT_AUTHORIZED_EXTENSION'));
        }

        $maxFileSize = $this->container->get(RuntimeConfig::class)['attach_config']['max_file_size']
            ?? $this->container->get(ParameterBagInterface::class)->get('max-upload-size');
        if ($uploadedFile->getSize() > $maxFileSize) {
            throw new \InvalidArgumentException(_t('ERROR_MAX_FILE_SIZE'));
        }

        // captured before move(): the SplFileInfo/UploadedFile object stops reflecting the
        // original tmp path (and getSize()/getMimeType() start failing) once moved away
        $size = (int)$uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType() ?? '';

        $storedFilename = $this->suggestFreeFilename($this->sanitizeFilename($originalFilename));
        $uploadedFile->move(self::STORAGE_DIR, $storedFilename);
        if (in_array($ext, ['svg', 'xml'], true)) {
            $this->container->get(HtmlPurifierService::class)->cleanFile(self::STORAGE_DIR . '/' . $storedFilename, $ext);
        }

        return [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
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

        // a file's own attributes are its data, so they live in the body -- metadata is
        // presentation and access only (ADR-0002's ticket-09 amendment)
        $saved = $this->pageManager->save($tag, [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'size' => $size,
            'mime_type' => $mimeType,
            'uploaded_from' => $ownerPageTag,
        ], '', true);
        if ($saved !== 0) {
            throw new \Exception("Could not save new file entry for '$originalFilename'.");
        }

        $this->tripleStore->create($tag, TripleStore::TYPE_URI, self::TRIPLES_FILE_TYPE, '', '');
        $this->fileTagCache[$tag] = true;

        // a file uploaded from its own form has no owning page, so there is no ACL to
        // inherit and the wiki's defaults are the right answer -- asking for the ACLs of
        // the empty tag is not
        if ($ownerPageTag !== '') {
            $readAcl = $this->aclService->load($ownerPageTag, 'read');
            if (!empty($readAcl['list'])) {
                $this->aclService->save($tag, 'read', $readAcl['list']);
            }
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
