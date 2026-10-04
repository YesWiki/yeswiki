<?php

namespace YesWiki\Admin\Service;

/** How a backup file is named: `2026-08-20T13-29-28_mydomain-ext-subfolder_archive.zip`, the source slug optional. */
class ArchiveFilename
{
    public const PATTERN = '/^(?P<date>\d{4}-\d{2}-\d{2})T(?P<time>\d{2}-\d{2}-\d{2})(?:_(?P<source>[a-z0-9-]+))?_archive(?:_(?P<type>only_files|only_db))?\.zip$/';

    public const MAX_SOURCE_LENGTH = 40;

    /** @return array{date: string, time: string, source: string, type: string}|array{} empty when the name is not a backup's */
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

    /** The address of a wiki, as the piece of a filename that stays readable. */
    public static function slug(string $baseUrl): string
    {
        $address = (string)preg_replace('#^https?://#i', '', trim($baseUrl));
        $address = (string)preg_replace(['/\?.*$/s', '#/(index|wakka)\.php#i'], '', $address);
        $slug = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($address)), '-');

        return trim(substr($slug, 0, self::MAX_SOURCE_LENGTH), '-');
    }

    /** A backup's name with the wiki it was taken from in it, replacing any source it already carried. */
    public static function withSource(string $filename, string $baseUrl): string
    {
        $parts = self::parse($filename);
        $slug = self::slug($baseUrl);
        if ($parts === [] || $slug === '') {
            return $filename;
        }
        $type = $parts['type'] === 'full' ? '' : "_{$parts['type']}";

        return "{$parts['date']}T{$parts['time']}_{$slug}_archive$type.zip";
    }

    /** A name for a backup taken now, of the given type, after the wiki at $baseUrl. */
    public static function forNow(string $type, string $baseUrl): string
    {
        $suffix = in_array($type, ['only_files', 'only_db'], true) ? "_$type" : '';

        return self::withSource((new \DateTime())->format('Y-m-d\TH-i-s') . "_archive$suffix.zip", $baseUrl);
    }
}
