<?php

namespace YesWiki\Content\Entity;

use YesWiki\Content\Service\CSVManager;

/** How a CSV file was written: its delimiter, its encoding, and whether a byte order mark preceded it. */
final class CsvDialect
{
    public const DELIMITERS = [',', ';', "\t", '|'];
    public const UTF8 = 'UTF-8';
    public const WINDOWS_1252 = 'Windows-1252';

    public function __construct(
        public readonly string $delimiter,
        public readonly string $encoding,
        public readonly bool $hadBom,
    ) {
    }

    /** The whole file as UTF-8, without its byte order mark. */
    public function toUtf8(string $content): string
    {
        if ($this->hadBom) {
            $content = substr($content, strlen(CSVManager::UTF8_BOM));
        }

        return $this->encoding === self::UTF8 ? $content : (string)mb_convert_encoding($content, self::UTF8, $this->encoding);
    }
}
