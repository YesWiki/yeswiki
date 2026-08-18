<?php

namespace YesWiki\Render\Service;

use YesWiki\Files\Exception\StorageException;
use YesWiki\Files\Service\Storage;

/** The wiki's own stylesheet: `custom/styles/custom.css` (ticket 30). */
class CustomCssService
{
    /** Instance-relative, matching how CoreAssets reads the directory. */
    public const DIRECTORY = 'custom/styles';

    /**
     * One well-known name, so "the wiki's custom CSS" is a thing rather than one of however many files a webmaster has dropped in the directory.
     */
    public const FILENAME = 'custom.css';

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

    /**
     * Whether saving would work, asked *before* offering the box rather than discovered on submit: an instance whose `custom/` is not writable (a read-only deploy, a wrong owner after an upgrade) can still be told so on the screen.
     */
    public function isWritable(): bool
    {
        return $this->storage->isWritable($this->path());
    }

    /**
     * @throws \RuntimeException when the file cannot be written -- the caller reports it,
     *                           because a stylesheet silently not saving is the worst of
     *                           the outcomes here
     */
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
}
