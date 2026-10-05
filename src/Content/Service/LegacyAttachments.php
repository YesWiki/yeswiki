<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * Doryphore's tools/attach uploads in files/ become File Content, and the `file="…"` parameters naming them are rewritten to tags, following the lookup rules Doryphore itself used.
 */
class LegacyAttachments
{
    public const LEGACY_NAME_PATTERN = '`^(.*)_(\d{14})_(\d{14})\.([^._]+)_?$`';

    private const FILE_PARAMETER = '/(?<![A-Za-z0-9_])((?:attach)?file)="([^"]*)"/i';

    private const INCLUDE_CALL = '/\{\{\s*include\b[^}]*?(?<![A-Za-z0-9_])page="([^"]+)"/i';

    public function __construct(
        private readonly FileManager $fileManager,
        private readonly PageManager $pageManager,
        private readonly Storage $storage,
        private readonly DbService $db,
    ) {
    }

    /** Strip the `_{pageDate}_{uploadDate}.{ext}[_]` suffix, and the `{pageTag}_` prefix when given, to get back the name the user uploaded. */
    public static function recoverOriginalFilename(string $rawFilename, ?string $stripPrefix): ?string
    {
        if (!preg_match(self::LEGACY_NAME_PATTERN, $rawFilename, $matches)) {
            return null;
        }
        $namePart = $matches[1];
        if ($stripPrefix !== null && str_starts_with($namePart, $stripPrefix . '_')) {
            $namePart = substr($namePart, strlen($stripPrefix) + 1);
        }

        return $namePart === '' ? null : $namePart . '.' . $matches[4];
    }

    /** Doryphore's Attach::sanitizeFilename() byte for byte: accented letters are dropped, not folded, because it ran on Latin-1 bytes. */
    public static function doryphoreKey(string $filename): string
    {
        $search = ['@[éèêëÊË]@i', '@[àâäÂÄ]@i', '@[îïÎÏ]@i', '@[ûùüÛÜ]@i', '@[ôöÔÖ]@i', '@[ç]@i', '@[ ]@i', '@[^a-zA-Z0-9_\.]@'];
        $replace = ['e', 'a', 'i', 'u', 'o', 'c', '_', ''];

        return (string)preg_replace($search, $replace, mb_convert_encoding($filename, 'ISO-8859-1', 'UTF-8'));
    }

    /** A looser key for names typed by hand: accents folded, case and punctuation ignored. */
    public static function foldedKey(string $filename): string
    {
        return (string)preg_replace('/[^a-z0-9.]/', '', strtolower(\URLify::downcode($filename)));
    }

    /**
     * Every file under $uploadPath named the Doryphore way, split by whether its owner page exists.
     *
     * @return array{owned: list<array{path: string, owner: string, name: string, uploaded: string, pageDate: string}>, orphans: list<string>}
     */
    public function scan(string $uploadPath): array
    {
        $uploadPath = rtrim($uploadPath, '/');
        $owned = [];
        $orphans = [];
        if (!$this->storage->directoryExists($uploadPath)) {
            return ['owned' => $owned, 'orphans' => $orphans];
        }

        foreach ($this->storage->directories($uploadPath) as $directory) {
            $owner = basename($directory);
            $ownerExists = $this->pageManager->tagExists($owner);
            foreach ($this->storage->files($directory) as $path) {
                if (!preg_match(self::LEGACY_NAME_PATTERN, basename($path))) {
                    continue;
                }
                $upload = $ownerExists ? $this->describe($path, $owner, null) : null;
                if ($upload === null) {
                    $orphans[] = $path;
                } else {
                    $owned[] = $upload;
                }
            }
        }

        foreach ($this->storage->files($uploadPath) as $path) {
            if (!preg_match(self::LEGACY_NAME_PATTERN, basename($path))) {
                continue;
            }
            $owner = $this->fileManager->guessOwnerPageTagFromLegacyFilename(basename($path));
            $upload = $owner === null ? null : $this->describe($path, $owner, $owner);
            if ($upload === null) {
                $orphans[] = $path;
            } else {
                $owned[] = $upload;
            }
        }

        return ['owned' => $owned, 'orphans' => $orphans];
    }

    /** @return array{path: string, owner: string, name: string, uploaded: string, pageDate: string}|null */
    private function describe(string $path, string $owner, ?string $stripPrefix): ?array
    {
        $raw = basename($path);
        $name = self::recoverOriginalFilename($raw, $stripPrefix);
        if ($name === null || !preg_match(self::LEGACY_NAME_PATTERN, $raw, $matches)) {
            return null;
        }

        return ['path' => $path, 'owner' => $owner, 'name' => $name, 'uploaded' => $matches[3], 'pageDate' => $matches[2]];
    }

    /**
     * The newest upload of each (owner page, name), the one Doryphore's lookup always served, and the older ones it hid.
     *
     * @param list<array{path: string, owner: string, name: string, uploaded: string, pageDate: string}> $uploads
     *
     * @return array{0: list<array{path: string, owner: string, name: string, uploaded: string, pageDate: string}>, 1: list<array{path: string, owner: string, name: string, uploaded: string, pageDate: string}>}
     */
    public static function newestOfEachName(array $uploads): array
    {
        usort($uploads, static fn (array $a, array $b): int => [$b['uploaded'], $b['pageDate'], $b['path']] <=> [$a['uploaded'], $a['pageDate'], $a['path']]);
        $newest = [];
        $superseded = [];
        foreach ($uploads as $upload) {
            $key = strtolower($upload['owner']) . "\0" . $upload['name'];
            if (isset($newest[$key])) {
                $superseded[] = $upload;
            } else {
                $newest[$key] = $upload;
            }
        }

        return [array_values($newest), $superseded];
    }

    /**
     * One File Content per (owner page, name) from its newest upload; a file some Content names in full (a Bazar field) stays in files/.
     *
     * @return array{created: array<string, string>, superseded: list<string>, orphans: list<string>, namedVerbatim: list<string>, alreadyMigrated: list<string>}
     */
    public function migrateUploads(string $uploadPath): array
    {
        $scan = $this->scan($uploadPath);
        [$newest, $superseded] = self::newestOfEachName($scan['owned']);
        $report = [
            'created' => [],
            'superseded' => array_map(static fn (array $u): string => $u['path'], $superseded),
            'orphans' => $scan['orphans'],
            'namedVerbatim' => [],
            'alreadyMigrated' => [],
        ];
        if ($newest === []) {
            return $report;
        }

        $index = $this->fileIndex();
        $verbatim = $this->namedVerbatim(array_map(static fn (array $u): string => basename($u['path']), $scan['owned']));
        foreach ($newest as $upload) {
            if (isset($verbatim[basename($upload['path'])])) {
                $report['namedVerbatim'][] = $upload['path'];
                continue;
            }
            if ($this->ownerHasName($index, $upload['owner'], $upload['name'])) {
                $report['alreadyMigrated'][] = $upload['path'];
                continue;
            }
            $tag = $this->migrateOne($upload);
            if ($tag !== null) {
                $report['created'][$upload['path']] = $tag;
            }
        }
        $report['superseded'] = array_values(array_filter(
            $report['superseded'],
            static fn (string $path): bool => !isset($verbatim[basename($path)])
        ));

        return $report;
    }

    /** @param array{path: string, owner: string, name: string, uploaded: string, pageDate: string} $upload */
    private function migrateOne(array $upload): ?string
    {
        $path = $upload['path'];
        $size = $this->storage->fileSize($path);
        $mimeType = $this->storage->withLocalCopy($path, static fn (string $local) => mime_content_type($local) ?: 'application/octet-stream');
        $storedFilename = $this->fileManager->suggestFreeFilename($this->fileManager->sanitizeFilename($upload['name']));
        try {
            $this->storage->copy($path, FileManager::STORAGE_DIR . '/' . $storedFilename);
        } catch (\Throwable) {
            return null;
        }
        $entry = $this->fileManager->create($upload['name'], $storedFilename, $upload['owner'], (int)$size, (string)$mimeType);
        $this->storage->delete($path);

        return (string)$entry['tag'];
    }

    /**
     * Which of these stored names some latest Content spells out in full.
     *
     * @param list<string> $basenames
     *
     * @return array<string, true>
     */
    private function namedVerbatim(array $basenames): array
    {
        if ($basenames === []) {
            return [];
        }
        $rows = $this->db->loadAll(
            "SELECT body FROM {$this->db->prefixTable('pages')} WHERE latest = 'Y' AND {$this->db->quoteIdentifier('type')} <> ?",
            [PageType::FILE]
        );
        $haystack = implode("\n", array_map(static fn (array $row): string => (string)$row['body'], $rows));

        $found = [];
        foreach ($basenames as $basename) {
            if (str_contains($haystack, $basename)) {
                $found[$basename] = true;
            }
        }

        return $found;
    }

    /**
     * Every File Content, by owner page, with the order it was created in (a later File Content of the same name is a later upload).
     *
     * @return array{tags: array<string, true>, byOwner: array<string, list<array{tag: string, name: string, stored: string, order: int}>>}
     */
    public function fileIndex(): array
    {
        $pages = $this->db->prefixTable('pages');
        $type = $this->db->quoteIdentifier('type');
        $firstIds = [];
        foreach ($this->db->loadAll("SELECT tag, MIN(id) AS first_id FROM {$pages} WHERE {$type} = ? GROUP BY tag", [PageType::FILE]) as $row) {
            $firstIds[(string)$row['tag']] = (int)$row['first_id'];
        }

        $index = ['tags' => [], 'byOwner' => []];
        foreach ($this->db->loadAll("SELECT tag, body FROM {$pages} WHERE latest = 'Y' AND {$type} = ?", [PageType::FILE]) as $row) {
            $tag = (string)$row['tag'];
            $body = PageBody::decode((string)$row['body']);
            $index['tags'][$tag] = true;
            $owner = strtolower((string)($body['uploaded_from'] ?? ''));
            if ($owner === '') {
                continue;
            }
            $index['byOwner'][$owner][] = [
                'tag' => $tag,
                'name' => (string)($body['original_filename'] ?? ''),
                'stored' => (string)($body['stored_filename'] ?? ''),
                'order' => $firstIds[$tag] ?? 0,
            ];
        }

        return $index;
    }

    /** @param array{tags: array<string, true>, byOwner: array<string, list<array{tag: string, name: string, stored: string, order: int}>>} $index */
    private function ownerHasName(array $index, string $owner, string $name): bool
    {
        foreach ($index['byOwner'][strtolower($owner)] ?? [] as $file) {
            if ($file['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * The File Content of $owner a written name meant, newest first, trying Doryphore's exact match before the looser keys.
     *
     * @param array{tags: array<string, true>, byOwner: array<string, list<array{tag: string, name: string, stored: string, order: int}>>} $index
     */
    public static function lookup(array $index, string $owner, string $writtenName): ?string
    {
        $files = $index['byOwner'][strtolower($owner)] ?? [];
        if ($files === []) {
            return null;
        }
        $underscored = str_replace(' ', '_', $writtenName);
        $tiers = [
            static fn (array $f): bool => $f['name'] === $underscored || $f['name'] === $writtenName,
            static fn (array $f): bool => self::doryphoreKey($f['name']) === self::doryphoreKey($writtenName)
                || self::doryphoreKey($f['stored']) === self::doryphoreKey($writtenName),
            static fn (array $f): bool => self::foldedKey($f['name']) === self::foldedKey($writtenName),
        ];
        foreach ($tiers as $matches) {
            $best = null;
            foreach ($files as $file) {
                if ($matches($file) && ($best === null || $file['order'] > $best['order'])) {
                    $best = $file;
                }
            }
            if ($best !== null) {
                return $best['tag'];
            }
        }

        return null;
    }

    /**
     * The tag a `file="…"` value on $hostTag means: `Page/name` belongs to Page, a bare name to the host, then to the single page including the host.
     *
     * @param array{tags: array<string, true>, byOwner: array<string, list<array{tag: string, name: string, stored: string, order: int}>>} $index
     * @param array<string, list<string>>                                                                                                  $includers lowercase included tag => tags including it
     */
    public static function resolve(array $index, array $includers, string $value, string $hostTag): ?string
    {
        if ($value === '' || isset($index['tags'][$value]) || str_contains($value, '://')) {
            return null;
        }
        if (!preg_match('`^((.+)/)?(.*)\.(.*)$`', $value, $matches)) {
            return null;
        }
        $name = $matches[3] . '.' . $matches[4];
        if ($matches[2] !== '') {
            return self::lookup($index, $matches[2], $name);
        }
        $found = self::lookup($index, $hostTag, $name);
        if ($found !== null) {
            return $found;
        }
        $viaIncluders = [];
        foreach ($includers[strtolower($hostTag)] ?? [] as $includer) {
            $tag = self::lookup($index, $includer, $name);
            if ($tag !== null) {
                $viaIncluders[$tag] = true;
            }
        }

        return count($viaIncluders) === 1 ? (string)array_key_first($viaIncluders) : null;
    }

    /**
     * Rewrite the `file=`/`attachfile=` parameters of every action call in $text, leaving `{# … #}` comments alone.
     *
     * @param callable(string): ?string $resolve value => tag, or null to leave it
     */
    public static function rewriteText(string $text, callable $resolve): string
    {
        if (!str_contains($text, 'file="') && !str_contains($text, 'FILE="')) {
            return $text;
        }
        $parts = preg_split('/(\{#.*?(?:#\}|\z))/s', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                continue;
            }
            $parts[$i] = (string)preg_replace_callback(
                '/\{\{(.*?)\}\}/s',
                static function (array $call) use ($resolve): string {
                    if (!preg_match('/^\s*[a-zA-Z0-9_-]+/', $call[1])) {
                        return $call[0];
                    }

                    return '{{' . preg_replace_callback(
                        self::FILE_PARAMETER,
                        static fn (array $p): string => $p[1] . '="' . ($resolve($p[2]) ?? $p[2]) . '"',
                        $call[1]
                    ) . '}}';
                },
                $part
            );
        }

        return implode('', $parts);
    }

    /**
     * Rewrite every revision's `file="…"` references that resolve to a File Content, in place; only the latest revisions report what stayed unresolved.
     *
     * @param list<string>|null $onlyTags restrict the sweep, which is what a test wants
     *
     * @return array{rewritten: list<string>, unresolved: list<string>}
     */
    public function rewriteReferences(?array $onlyTags = null): array
    {
        $index = $this->fileIndex();
        $includers = $this->includers();
        $pages = $this->db->prefixTable('pages');
        $sql = "SELECT id, tag, latest, body FROM {$pages} WHERE {$this->db->quoteIdentifier('type')} <> ? AND "
            . $this->db->jsonAsText('body') . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX;
        $params = [PageType::FILE, SqlParameters::likeContains('file=')];
        if ($onlyTags !== null) {
            if ($onlyTags === []) {
                return ['rewritten' => [], 'unresolved' => []];
            }
            $sql .= ' AND tag IN (' . SqlParameters::placeholders(count($onlyTags)) . ')';
            $params = array_merge($params, $onlyTags);
        }

        $rewritten = [];
        $unresolved = [];
        foreach ($this->db->loadAll($sql, $params) as $row) {
            $tag = (string)$row['tag'];
            $isLatest = $row['latest'] === 'Y';
            $resolve = static function (string $value) use ($index, $includers, $tag, $isLatest, &$unresolved): ?string {
                $found = self::resolve($index, $includers, $value, $tag);
                if ($found === null && $isLatest && !isset($index['tags'][$value]) && str_contains($value, '.') && !str_contains($value, '://')) {
                    $unresolved["{$tag}: {$value}"] = true;
                }

                return $found;
            };

            $stored = (string)$row['body'];
            $isJson = is_array(json_decode($stored, true));
            $body = PageBody::decode($stored);
            $changed = false;
            array_walk_recursive($body, static function (&$value) use ($resolve, &$changed): void {
                if (!is_string($value)) {
                    return;
                }
                $new = self::rewriteText($value, $resolve);
                if ($new !== $value) {
                    $value = $new;
                    $changed = true;
                }
            });
            if (!$changed) {
                continue;
            }
            $this->db->query(
                "UPDATE {$pages} SET body = ? WHERE id = ?",
                [$isJson ? PageBody::encode($body) : PageBody::content($body), (string)$row['id']]
            );
            $this->pageManager->forget($tag);
            $rewritten[$tag] = true;
        }

        return ['rewritten' => array_keys($rewritten), 'unresolved' => array_keys($unresolved)];
    }

    /** @return array<string, list<string>> lowercase included tag => the tags whose latest body includes it */
    private function includers(): array
    {
        $rows = $this->db->loadAll(
            "SELECT tag, body FROM {$this->db->prefixTable('pages')} WHERE latest = 'Y' AND "
            . $this->db->jsonAsText('body') . ' LIKE ?' . SqlParameters::LIKE_CLAUSE_SUFFIX,
            [SqlParameters::likeContains('include')]
        );
        $includers = [];
        foreach ($rows as $row) {
            $markup = implode("\n", array_filter(PageBody::decode((string)$row['body']), 'is_string'));
            if (preg_match_all(self::INCLUDE_CALL, $markup, $matches)) {
                foreach ($matches[1] as $included) {
                    $includers[strtolower(trim($included))][] = (string)$row['tag'];
                }
            }
        }

        return $includers;
    }

    /**
     * Put back in files/ the Bazar field files an earlier migration moved into File Content, since entry fields read them from there.
     *
     * @param list<string>|null $onlyTags restrict the sweep, which is what a test wants
     *
     * @return list<string> the files/ paths restored
     */
    public function restoreEntryFieldFiles(string $uploadPath, bool $safeMode, ?array $onlyTags = null): array
    {
        $uploadPath = rtrim($uploadPath, '/');
        $sql = "SELECT tag, body FROM {$this->db->prefixTable('pages')} WHERE latest = 'Y' AND {$this->db->quoteIdentifier('type')} = ?";
        $params = [PageType::ENTRY];
        if ($onlyTags !== null) {
            if ($onlyTags === []) {
                return [];
            }
            $sql .= ' AND tag IN (' . SqlParameters::placeholders(count($onlyTags)) . ')';
            $params = array_merge($params, $onlyTags);
        }
        $rows = $this->db->loadAll($sql, $params);
        if ($rows === []) {
            return [];
        }

        $index = $this->fileIndex();
        $restored = [];
        foreach ($rows as $row) {
            $tag = (string)$row['tag'];
            foreach (PageBody::decode((string)$row['body']) as $value) {
                if (!is_string($value) || str_contains($value, '/') || !preg_match(self::LEGACY_NAME_PATTERN, $value)) {
                    continue;
                }
                $target = $safeMode ? "{$uploadPath}/{$value}" : "{$uploadPath}/{$tag}/{$value}";
                if ($this->storage->exists($target)) {
                    continue;
                }
                $name = self::recoverOriginalFilename($value, $safeMode ? $tag : null);
                $fileTag = $name === null ? null : $this->exactLookup($index, $tag, $name);
                $source = $fileTag === null ? null : $this->fileManager->getPhysicalPath($fileTag);
                if ($source === null) {
                    continue;
                }
                $this->storage->copy($source, $target);
                $restored[] = $target;
            }
        }

        return $restored;
    }

    /** @param array{tags: array<string, true>, byOwner: array<string, list<array{tag: string, name: string, stored: string, order: int}>>} $index */
    private function exactLookup(array $index, string $owner, string $name): ?string
    {
        $best = null;
        foreach ($index['byOwner'][strtolower($owner)] ?? [] as $file) {
            if ($file['name'] === $name && ($best === null || $file['order'] > $best['order'])) {
                $best = $file;
            }
        }

        return $best['tag'] ?? null;
    }
}
