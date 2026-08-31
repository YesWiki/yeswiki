<?php

namespace YesWiki\Core\Service;

/**
 * The one place that knows how a backup file is named.
 *
 * `2026-08-20T13-29-28_mydomain-ext-subfolder_archive.zip`: a backup carries the address of the
 * wiki it was taken from, so it can still be told apart once downloaded. Backups made before
 * that, and by a wiki with no usable address, carry no source: `2026-08-20T13-29-28_archive.zip`.
 */
class ArchiveFilename
{
    public const PATTERN = '/^(?P<date>\d{4}-\d{2}-\d{2})T(?P<time>\d{2}-\d{2}-\d{2})(?:_(?P<source>[a-z0-9-]+))?_archive(?:_(?P<type>only_files|only_db))?\.zip$/';
    public const MAX_SOURCE_LENGTH = 40;

    /**
     * @return array{date:string,time:string,source:string,type:string} empty when the name is not a backup
     */
    public static function parse(string $filename): array
    {
        if (!preg_match(self::PATTERN, $filename, $matches)) {
            return [];
        }

        return [
            'date' => $matches['date'],
            'time' => $matches['time'],
            'source' => $matches['source'] ?? '',
            'type' => empty($matches['type']) ? 'full' : $matches['type'],
        ];
    }

    /**
     * The address of a wiki, as the piece of a filename that stays readable.
     */
    public static function slug(string $baseUrl): string
    {
        $address = preg_replace('#^https?://#i', '', trim($baseUrl));
        $address = preg_replace('#/(index|wakka)\.php#i', '', (string)$address);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$address));
        $slug = trim((string)$slug, '-');

        return trim(substr($slug, 0, self::MAX_SOURCE_LENGTH), '-');
    }

    /**
     * Rename a backup after the wiki it was taken from, replacing any source it already carried.
     */
    public static function withSource(string $filename, string $baseUrl): string
    {
        $parts = self::parse($filename);
        $slug = self::slug($baseUrl);
        if (empty($parts) || $slug === '') {
            return $filename;
        }
        $type = $parts['type'] === 'full' ? '' : "_{$parts['type']}";

        return "{$parts['date']}T{$parts['time']}_{$slug}_archive$type.zip";
    }
}
