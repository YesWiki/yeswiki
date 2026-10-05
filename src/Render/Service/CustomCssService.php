<?php

namespace YesWiki\Render\Service;

use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Service\PageManager;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;

/** The wiki's own stylesheet: `custom/styles/custom.css` (ticket 30). */
class CustomCssService
{
    /** Instance-relative, matching how CoreAssets reads the directory. */
    public const DIRECTORY = 'custom/styles';

    /** One well-known name, so "the wiki's custom CSS" is one file rather than whatever sits in the directory. */
    public const FILENAME = 'custom.css';

    /** What absorbPage() did: there was no page, its CSS was already there, or it was written or appended. */
    public const NO_PAGE = 'no-page';
    public const ALREADY_THERE = 'already-there';
    public const WRITTEN = 'written';
    public const APPENDED = 'appended';

    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function path(): string
    {
        return self::DIRECTORY . '/' . self::FILENAME;
    }

    public function exists(): bool
    {
        return $this->storage->fileExists($this->path());
    }

    public function read(): string
    {
        if (!$this->exists()) {
            return '';
        }

        return $this->storage->read($this->path());
    }

    /** Whether saving would work, asked before offering the box rather than discovered on submit. */
    public function isWritable(): bool
    {
        return $this->storage->isWritable($this->path());
    }

    /** @throws \RuntimeException when the file cannot be written, which the caller must report */
    public function write(string $css): void
    {
        if (trim($css) === '') {
            if ($this->exists()) {
                try {
                    $this->storage->delete($this->path());
                } catch (StorageException $exception) {
                    throw new \RuntimeException(sprintf('Cannot remove %s', $this->path()), 0, $exception);
                }
            }

            return;
        }

        try {
            $this->storage->write($this->path(), $css);
        } catch (StorageException $exception) {
            throw new \RuntimeException(sprintf('Cannot write %s', $this->path()), 0, $exception);
        }
    }

    /** Whether the file already holds this CSS, whitespace aside. */
    public function contains(string $css): bool
    {
        $needle = self::normalised($css);

        return $needle === '' || str_contains(self::normalised($this->read()), $needle);
    }

    /** Add CSS to the file unless it is already in it, and say which of the three happened. */
    public function absorb(string $css): string
    {
        $css = trim($css);
        if ($this->contains($css)) {
            return self::ALREADY_THERE;
        }

        $current = rtrim($this->read());
        if ($current === '') {
            $this->write($css . "\n");

            return self::WRITTEN;
        }

        $this->write($current . "\n\n" . $css . "\n");

        return self::APPENDED;
    }

    /** Move a page's CSS into the file and delete the page, every revision of it. */
    public function absorbPage(PageManager $pages, string $tag, bool $throughMarkdown): string
    {
        $page = $pages->getOne($tag, null, false, true);
        if ($page === null) {
            return self::NO_PAGE;
        }

        $css = PageBody::content(is_array($page['body'] ?? null) ? $page['body'] : []);
        $done = $this->absorb($throughMarkdown ? self::withoutHardBreaks($css) : $css);
        $pages->deleteOrphaned($tag);

        return $done;
    }

    /** CSS with the `\` the Markdown conversion put at the end of its lines taken off again. */
    public static function withoutHardBreaks(string $css): string
    {
        return (string)preg_replace('/[ \t]*\\\\[ \t]*$/m', '', $css);
    }

    private static function normalised(string $css): string
    {
        return trim((string)preg_replace('/\s+/', ' ', self::withoutHardBreaks($css)));
    }
}
