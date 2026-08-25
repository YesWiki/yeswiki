<?php

namespace YesWiki\Core;

/**
 * Freshness by content hash instead of mtime, for files rewritten programmatically in rapid succession (mtime has 1-second granularity, so a FileResource can miss the second of two same-second writes).
 */
class ConfigFileHashResource implements \Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    private string $file;
    private string $hash;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->hash = (string)@md5_file($file);
    }

    public function isFresh(int $timestamp): bool
    {
        return (string)@md5_file($this->file) === $this->hash;
    }

    public function __toString(): string
    {
        return 'confighash.' . $this->file . '.' . $this->hash;
    }
}
