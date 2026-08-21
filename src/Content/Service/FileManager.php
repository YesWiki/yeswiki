<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use YesWiki\Content\Entity\PageType;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\HtmlPurifierService;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\TripleStore;

/**
 * Attached files are their own Content type (ticket 17, formerly tools/attach) -- a `pages` row per uploaded file, tagged via TripleStore::TYPE_URI the same way FormManager/UserManager tag forms/users, with its own independent ACL.
 */
class FileManager
{
    /** The families a list of files can be narrowed to, in the order a picker offers them. */
    public const FAMILIES = ['image', 'video', 'audio', 'document', 'other'];

    /** Extensions that decide a family on their own, ahead of the MIME type. */
    private const FAMILY_EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'avif', 'ico', 'tif', 'tiff'],
        'video' => ['mp4', 'webm', 'ogv', 'mov', 'avi', 'mkv', 'm4v', 'mpg', 'mpeg', 'wmv'],
        'audio' => ['mp3', 'ogg', 'oga', 'wav', 'flac', 'm4a', 'aac', 'opus', 'wma', 'mid', 'midi'],
        'document' => [
            'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md',
            'xls', 'xlsx', 'ods', 'csv',
            'ppt', 'pptx', 'odp', 'epub',
        ],
    ];

    public const STORAGE_DIR = 'private/files';

    protected ContainerInterface $container;
    protected TripleStore $tripleStore;
    protected PageManager $pageManager;
    protected AclService $aclService;
    protected Storage $storage;

    public function __construct(
        ContainerInterface $container,
        TripleStore $tripleStore,
        PageManager $pageManager,
        AclService $aclService,
        Storage $storage
    ) {
        $this->container = $container;
        $this->tripleStore = $tripleStore;
        $this->pageManager = $pageManager;
        $this->aclService = $aclService;
        $this->storage = $storage;
    }

    /** "2M"/"512k"-style size string to bytes (historic Wiki::parse_size()). */
    public static function parseSize(mixed $size): int
    {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', (string)$size);
        $size = preg_replace('/[^0-9\.]/', '', (string)$size);
        if ($unit) {
            return intval(round((int)$size * pow(1024, (int)stripos('bkmgtpezy', $unit[0]))));
        }

        return intval(round((int)$size));
    }

    /**
     * Upload size limit in bytes: the strictest of PHP's upload_max_filesize and post_max_size and the wiki's max_file_size config (historic Wiki::file_upload_max_size()).
     */
    public function uploadMaxSize(): int
    {
        return self::uploadMaxSizeFromConfig($this->container->get(RuntimeConfig::class)->getValue('max_file_size'));
    }

    /**
     * Same, from a raw max_file_size config value — for YesWikiKernel::build(), which runs before any service can be constructed.
     */
    public static function uploadMaxSizeFromConfig(mixed $maxFileSizeConfig): int
    {
        $confMaxFileSize = !empty($maxFileSizeConfig) && !is_array($maxFileSizeConfig)
            ? self::parseSize(trim((string)$maxFileSizeConfig))
            : 0;

        $postMaxSize = self::parseSize(ini_get('post_max_size'));

        $uploadMax = self::parseSize(ini_get('upload_max_filesize'));

        return min(array_filter([$confMaxFileSize, $postMaxSize, $uploadMax]) ?: [DEFAULT_MAX_UPLOAD_SIZE]);
    }

    /** The lowercase extension a file is named with, without the dot ('' when it has none). */
    public static function extensionOf(string $originalFilename): string
    {
        return strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    }

    /**
     * Which of self::FAMILIES this file belongs to -- what a picker filters by, and what decides which icon stands in for a file that cannot be shown as a thumbnail.
     */
    public static function familyOf(string $mimeType, string $originalFilename): string
    {
        $extension = self::extensionOf($originalFilename);
        foreach (self::FAMILY_EXTENSIONS as $family => $extensions) {
            if (in_array($extension, $extensions, true)) {
                return $family;
            }
        }

        $mimeType = strtolower($mimeType);
        foreach (['image', 'video', 'audio'] as $family) {
            if (str_starts_with($mimeType, "$family/")) {
                return $family;
            }
        }

        if (str_starts_with($mimeType, 'text/')
            || preg_match('#^application/(pdf|rtf|msword|vnd\.(ms-|oasis\.opendocument|openxmlformats-))#', $mimeType) === 1) {
            return 'document';
        }

        return 'other';
    }

    public function isFileTag(string $tag): bool
    {
        if (empty($tag)) {
            return false;
        }

        return $this->pageManager->isType($tag, PageType::FILE);
    }

    /**
     * @return list<string>
     */
    public function getAllFileTags(): array
    {
        return $this->pageManager->tagsOfType(PageType::FILE);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOne(string $tag): ?array
    {
        $page = $this->pageManager->getOne($tag, null, true, true);
        if (empty($page) || ($page['type'] ?? null) !== PageType::FILE) {
            return null;
        }
        $body = is_array($page['body'] ?? null) ? $page['body'] : [];

        return array_merge(['tag' => $tag], $body);
    }

    public function getPhysicalPath(string $tag): ?string
    {
        $entry = $this->getOne($tag);
        if (empty($entry['stored_filename'])) {
            return null;
        }
        $path = self::STORAGE_DIR . '/' . $entry['stored_filename'];

        return $this->storage->fileExists($path) ? $path : null;
    }

    /**
     * Validate an upload and put its bytes on disk, returning the attributes a file Content is made of.
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

        $size = (int)$uploadedFile->getSize();
        $mimeType = $uploadedFile->getMimeType() ?? '';

        $storedFilename = $this->suggestFreeFilename($this->sanitizeFilename($originalFilename));
        $stored = self::STORAGE_DIR . '/' . $storedFilename;
        $this->storage->writeFrom($stored, $uploadedFile->getPathname());
        if (in_array($ext, ['svg', 'xml'], true)) {
            $purifier = $this->container->get(HtmlPurifierService::class);
            $this->storage->withLocalTarget($stored, fn (string $local) => $purifier->cleanFile($local, $ext));
        }

        return [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'size' => $size,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Register an already-uploaded/moved physical file as a new file-entry, seeding its ACL from $ownerPageTag's current read ACL.
     *
     * @return array<string, mixed>
     */
    public function create(string $originalFilename, string $storedFilename, string $ownerPageTag, int $size, string $mimeType): array
    {
        $tag = $this->pageManager->suggestFreeTag($this->slugForTag($originalFilename));

        $saved = $this->pageManager->save($tag, [
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'size' => $size,
            'mime_type' => $mimeType,
            'uploaded_from' => $ownerPageTag,
        ], '', true, null, PageType::FILE);
        if ($saved !== 0) {
            throw new \Exception("Could not save new file entry for '$originalFilename'.");
        }
        $this->pageManager->cacheType($tag, PageType::FILE);

        if ($ownerPageTag !== '') {
            $readAcl = $this->aclService->load($ownerPageTag, 'read');
            if (!empty($readAcl['list'])) {
                $this->aclService->save($tag, 'read', $readAcl['list']);
            }
        }

        $created = $this->getOne($tag);

        if ($created === null) {
            throw new \Exception("the file '$tag' was written but cannot be read back");
        }

        return $created;
    }

    /**
     * Resolve a collision-free physical filename under files/, matching PageManager::suggestFreeTag()'s try-then-suffix pattern but for a filename.
     */
    public function suggestFreeFilename(string $sanitizedFilename): string
    {
        if (!$this->storage->exists(self::STORAGE_DIR . '/' . $sanitizedFilename)) {
            return $sanitizedFilename;
        }

        $pathinfo = pathinfo($sanitizedFilename);
        $base = $pathinfo['filename'];
        $ext = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';

        for ($suffix = 2; $suffix <= 1000; $suffix++) {
            $candidate = $base . '-' . $suffix . $ext;
            if (!$this->storage->exists(self::STORAGE_DIR . '/' . $candidate)) {
                return $candidate;
            }
        }

        throw new \Exception("Could not find a free filename for '$sanitizedFilename'.");
    }

    /** Ported from tools/attach/libs/attach.lib.php's Attach::sanitizeFilename(). */
    public function sanitizeFilename(string $filename): string
    {
        $search = ['@[éèêëÊË]@i', '@[àâäÂÄ]@i', '@[îïÎÏ]@i', '@[ûùüÛÜ]@i', '@[ôöÔÖ]@i', '@[ç]@i', '@[ ]@i', '@[^a-zA-Z0-9_\.\-]@'];
        $replace = ['e', 'a', 'i', 'u', 'o', 'c', '_', ''];

        return (string)preg_replace($search, $replace, mb_convert_encoding($filename, 'ISO-8859-1', 'UTF-8'));
    }

    public function delete(string $tag): void
    {
        if (!$this->isFileTag($tag)) {
            return;
        }
        $entry = $this->getOne($tag);
        if (!empty($entry['stored_filename'])) {
            $path = self::STORAGE_DIR . '/' . $entry['stored_filename'];
            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
            }
        }
        $this->pageManager->deleteOrphaned($tag);
    }

    private function slugForTag(string $originalFilename): string
    {
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $slug = \URLify::slug($base);

        return empty($slug) ? 'file' : $slug;
    }

    /**
     * Legacy tools/attach filenames were prefixed with their owning page's tag ({pageTag}_{name}_{pageDate}_{uploadDate}.{ext}, see attach.lib.php's GetFullFilename()) -- both the ticket 17 migration (converting existing uploads into file entries) and the image-resize-cache API route (whose raw $filename argument, sourced from Bazar's own image/file fields which upload through this same legacy convention, was never migrated to a file-entry tag) need to recover that owning page tag from an arbitrary such filename, to enforce a real read-ACL check where none existed before.
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
