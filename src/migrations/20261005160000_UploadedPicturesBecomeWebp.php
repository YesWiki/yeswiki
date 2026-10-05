<?php

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\FileManager;
use YesWiki\Content\Service\PageManager;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageShrinker;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Search\Service\SearchIndexer;

/** Every JPEG and PNG uploaded before Ectoplasme, as File Content or as a Bazar field's file, rewritten as WebP and fitted to the upload bounds. */
class UploadedPicturesBecomeWebp extends YesWikiMigration
{
    private const LISTED = 20;

    private int $before = 0;

    private int $after = 0;

    /** @var list<string> */
    private array $failed = [];

    public function run()
    {
        $format = $this->params->has('image-upload-format') ? $this->params->get('image-upload-format') : 'image/webp';
        $format = is_string($format) ? $format : '';
        if ($format !== 'image/webp') {
            $this->say("image-upload-format is '{$format}', so pictures already stored were left as they are.");

            return;
        }
        @ini_set('memory_limit', '2G');

        $bounds = [
            $this->number('image-upload-max-width', 1920),
            $this->number('image-upload-max-height', 1920),
            (int)round($this->fraction('image-upload-quality', 0.82) * 100),
        ];

        $uploadPath = rtrim($this->getService(AttachedFilePaths::class)->uploadPath(), '/');
        $files = $this->shrinkFileContent($this->getService(FileManager::class)->getAllFileTags(), ...$bounds);
        [$uploads, $touched] = $this->shrinkUploads($this->getService(Storage::class)->glob("{$uploadPath}/*"), ...$bounds);
        if ($touched !== []) {
            $this->getService(SearchIndexer::class)->enqueue($touched);
        }

        if ($files + $uploads > 0) {
            $this->say(sprintf(
                '%d picture(s) rewritten as WebP, none larger than %dx%d (%d attached file(s), %d Bazar field file(s)): %s MB down to %s MB.',
                $files + $uploads,
                $bounds[0],
                $bounds[1],
                $files,
                $uploads,
                number_format($this->before / 1048576, 1),
                number_format($this->after / 1048576, 1)
            ));
        }
        if ($touched !== []) {
            $this->say('the new file names were written into: ' . self::listed($touched));
        }
        if ($this->failed !== []) {
            $this->say(count($this->failed) . ' picture(s) could not be converted and were kept as they are: ' . self::listed($this->failed));
        }
    }

    /**
     * Each File Content's stored picture, replaced in place; its tag, which is what pages name, does not change.
     *
     * @param list<string> $tags
     */
    public function shrinkFileContent(array $tags, int $maxWidth, int $maxHeight, int $quality): int
    {
        $fileManager = $this->getService(FileManager::class);
        $pageManager = $this->getService(PageManager::class);
        $shrinker = $this->getService(ImageShrinker::class);
        $storage = $this->getService(Storage::class);

        $done = 0;
        foreach ($tags as $tag) {
            $entry = $fileManager->getOne($tag);
            $stored = (string)($entry['stored_filename'] ?? '');
            $path = FileManager::STORAGE_DIR . '/' . $stored;
            if ($entry === null || $stored === '' || !$storage->exists($path) || !$shrinker->isConvertible($path)) {
                continue;
            }
            $newStored = $fileManager->suggestFreeFilename(pathinfo($stored, PATHINFO_FILENAME) . '.webp');
            $newPath = FileManager::STORAGE_DIR . '/' . $newStored;
            if (!$shrinker->shrink($path, $newPath, $maxWidth, $maxHeight, $quality)) {
                $this->failed[] = $path;
                continue;
            }

            $this->before += $storage->fileSize($path);
            $this->after += $storage->fileSize($newPath);
            unset($entry['tag']);
            $entry['stored_filename'] = $newStored;
            $entry['original_filename'] = pathinfo((string)($entry['original_filename'] ?? $stored), PATHINFO_FILENAME) . '.webp';
            $entry['size'] = $storage->fileSize($newPath);
            $entry['mime_type'] = 'image/webp';
            $pageManager->save($tag, $entry, '', true, null, PageType::FILE);
            $storage->delete($path);
            $done++;
        }

        return $done;
    }

    /**
     * The pictures still in files/ that Bazar fields name in full, renamed to .webp and named so in every revision of every Content, and in the configuration, that said the old name.
     *
     * @param list<string> $paths
     *
     * @return array{0: int, 1: list<string>} how many, and the tags rewritten
     */
    public function shrinkUploads(array $paths, int $maxWidth, int $maxHeight, int $quality): array
    {
        $shrinker = $this->getService(ImageShrinker::class);
        $storage = $this->getService(Storage::class);

        $done = 0;
        $touched = [];
        $renamed = [];
        foreach ($paths as $path) {
            if (!$shrinker->isConvertible($path)) {
                continue;
            }
            $oldName = basename($path);
            $newName = pathinfo($oldName, PATHINFO_FILENAME) . '.webp';
            $newPath = dirname($path) . "/{$newName}";
            if ($storage->exists($newPath) || !$shrinker->shrink($path, $newPath, $maxWidth, $maxHeight, $quality)) {
                $this->failed[] = $path;
                continue;
            }

            $this->before += $storage->fileSize($path);
            $this->after += $storage->fileSize($newPath);
            $touched = [...$touched, ...$this->rename($oldName, $newName)];
            $renamed[$oldName] = $newName;
            $storage->delete($path);
            foreach ($storage->glob('cache/' . pathinfo($oldName, PATHINFO_FILENAME) . '*') as $thumbnail) {
                $storage->delete($thumbnail);
            }
            $done++;
        }
        $this->renameInConfiguration($renamed);

        return [$done, array_values(array_unique($touched))];
    }

    /**
     * The configuration's values that named a renamed picture, such as the logo the layout carried over from PageTitre.
     *
     * @param array<string, string> $renamed old name => new name
     */
    private function renameInConfiguration(array $renamed): void
    {
        if ($renamed === []) {
            return;
        }
        $config = $this->getService(ConfigurationService::class)->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();

        $changes = [];
        foreach ($config as $key => $value) {
            $new = $value;
            if (is_string($new)) {
                $new = strtr($new, $renamed);
            } elseif (is_array($new)) {
                array_walk_recursive($new, static function (&$item) use ($renamed): void {
                    if (is_string($item)) {
                        $item = strtr($item, $renamed);
                    }
                });
            }
            if ($new !== $value) {
                $changes[$key] = $new;
            }
        }
        if ($changes === []) {
            return;
        }
        foreach ($changes as $key => $value) {
            $config[$key] = $value;
        }
        $config->write();
    }

    /**
     * @return list<string> the tags whose Content said $oldName
     */
    private function rename(string $oldName, string $newName): array
    {
        $pages = $this->dbService->prefixTable('pages');
        $rows = $this->dbService->loadAll(
            "SELECT id, tag, body FROM {$pages} WHERE {$this->dbService->jsonAsText('body')} LIKE ?" . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains($oldName)]
        );

        $tags = [];
        foreach ($rows as $row) {
            $stored = (string)$row['body'];
            $isJson = is_array(json_decode($stored, true));
            $body = PageBody::decode($stored);
            array_walk_recursive($body, static function (&$value) use ($oldName, $newName): void {
                if (is_string($value)) {
                    $value = str_replace($oldName, $newName, $value);
                }
            });
            $this->dbService->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [$isJson ? PageBody::encode($body) : PageBody::content($body), (string)$row['id']]
            );
            $tags[] = (string)$row['tag'];
        }

        return $tags;
    }

    private function fraction(string $key, float $default): float
    {
        $value = $this->params->has($key) ? $this->params->get($key) : $default;

        return is_numeric($value) ? (float)$value : $default;
    }

    private function number(string $key, int $default): int
    {
        $value = $this->params->has($key) ? $this->params->get($key) : $default;

        return is_numeric($value) ? (int)$value : $default;
    }

    /** @param list<string> $items */
    private static function listed(array $items): string
    {
        return implode(', ', array_slice($items, 0, self::LISTED)) . (count($items) > self::LISTED ? ', …' : '');
    }
}
