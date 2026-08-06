<?php

namespace YesWiki\Render\Service;

/**
 * The wiki's own stylesheet: `custom/styles/custom.css` (ticket 30).
 *
 * This used to be a wiki page called `PageCss`, read by CoreAssets and **inlined into every
 * page**. It had to be inlined: the `/raw` handler serves `text/plain`, and a browser in
 * standards mode refuses a stylesheet link that is not `text/css`. A real file has no such
 * problem -- it is an ordinary, cacheable `<link>`, and `custom/styles/*.css` is a directory
 * CoreAssets already loads, so nothing new had to be taught to load it.
 *
 * Instance-relative, not source-relative. `custom/` belongs to the instance, and on a farm
 * the source tree is shared between wikis that must not share a stylesheet -- the same
 * distinction ThemeManager draws between `getcwd() . '/custom/themes'` and
 * `YESWIKI_SOURCE_DIR . '/themes'`.
 */
class CustomCssService
{
    /** Instance-relative, matching how CoreAssets reads the directory. */
    public const DIRECTORY = 'custom/styles';

    /**
     * One well-known name, so "the wiki's custom CSS" is a thing rather than one of however
     * many files a webmaster has dropped in the directory. Everything else in there is
     * theirs and is left alone.
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
     * Whether saving would work, asked *before* offering the box rather than discovered on
     * submit: an instance whose `custom/` is not writable (a read-only deploy, a wrong
     * owner after an upgrade) can still be told so on the screen.
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

        // an empty stylesheet is a deletion: leaving a 0-byte file behind means every page
        // keeps a <link> to nothing
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
