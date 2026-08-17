<?php

namespace YesWiki\Render\Service;

/** The wiki's own stylesheet: `custom/styles/custom.css` (ticket 30). */
class CustomCssService
{
    /** Instance-relative, matching how CoreAssets reads the directory. */
    public const DIRECTORY = 'custom/styles';

    /**
     * One well-known name, so "the wiki's custom CSS" is a thing rather than one of however many files a webmaster has dropped in the directory.
     */
    public const FILENAME = 'custom.css';

    public function path(): string
    {
        return self::DIRECTORY . '/' . self::FILENAME;
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function read(): string
    {
        if (!$this->exists()) {
            return '';
        }

        return (string)file_get_contents($this->path());
    }

    /**
     * Whether saving would work, asked *before* offering the box rather than discovered on submit: an instance whose `custom/` is not writable (a read-only deploy, a wrong owner after an upgrade) can still be told so on the screen.
     */
    public function isWritable(): bool
    {
        if ($this->exists()) {
            return is_writable($this->path());
        }

        return is_dir(self::DIRECTORY) ? is_writable(self::DIRECTORY) : $this->parentIsWritable();
    }

    /**
     * @throws \RuntimeException when the file cannot be written -- the caller reports it,
     *                           because a stylesheet silently not saving is the worst of
     *                           the outcomes here
     */
    public function write(string $css): void
    {
        if (!is_dir(self::DIRECTORY) && !@mkdir(self::DIRECTORY, 0o755, true) && !is_dir(self::DIRECTORY)) {
            throw new \RuntimeException(sprintf('Cannot create %s', self::DIRECTORY));
        }

        if (trim($css) === '') {
            if ($this->exists() && !@unlink($this->path())) {
                throw new \RuntimeException(sprintf('Cannot remove %s', $this->path()));
            }

            return;
        }

        if (@file_put_contents($this->path(), $css) === false) {
            throw new \RuntimeException(sprintf('Cannot write %s', $this->path()));
        }
    }

    private function parentIsWritable(): bool
    {
        $parent = dirname(self::DIRECTORY);

        return is_dir($parent) && is_writable($parent);
    }
}
