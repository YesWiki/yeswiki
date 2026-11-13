<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Core\YesWikiLoader;
use YesWiki\Files\Service\LocalFiles;

/** Writes operator settings into `private/.env`, keeping every other line as it was; a list is written as JSON, a file left empty is removed (replicas 04). */
class EnvFile
{
    /** @param string|null $instanceDir another wiki's folder, whose file is written without touching this process's environment */
    public function __construct(private readonly LocalFiles $files = new LocalFiles(), private readonly ?string $instanceDir = null)
    {
    }

    public function path(): string
    {
        return ($this->instanceDir ?? (\defined('YESWIKI_INSTANCE_DIR') ? YESWIKI_INSTANCE_DIR : (string)getcwd())) . '/private/.env';
    }

    /**
     * Sets those keys, null removing one; false when the file cannot be written.
     *
     * @param array<string, mixed> $settings
     */
    public function store(array $settings): bool
    {
        if ($settings === []) {
            return true;
        }
        $path = $this->path();
        if (!$this->files->makeDirectory(dirname($path))) {
            return false;
        }
        $lines = $this->files->isFile($path) ? explode("\n", rtrim($this->files->read($path), "\n")) : [];
        foreach ($settings as $key => $value) {
            $name = strtoupper($key);
            $lines = array_values(array_filter($lines, static fn (string $line): bool => preg_match('/^\s*(export\s+)?' . preg_quote($name, '/') . '\s*=/', $line) !== 1));
            if ($value !== null) {
                $lines[] = $name . '=' . self::quote($value);
            }
        }

        $kept = array_filter($lines, static fn (string $line): bool => trim($line) !== '');
        if ($kept === []) {
            if (!$this->files->remove($path)) {
                return false;
            }
        } elseif (!$this->files->writeAtomically($path, implode("\n", $lines) . "\n")) {
            return false;
        }
        if ($this->instanceDir !== null) {
            YesWikiLoader::forgetEnvFile($this->instanceDir);

            return true;
        }
        YesWikiLoader::forgetEnvFile();
        $fromTheFile = array_filter(explode(',', (string)getenv('SYMFONY_DOTENV_VARS')));
        foreach ($settings as $key => $value) {
            $name = strtoupper($key);
            if (getenv($name) === false || in_array($name, $fromTheFile, true)) {
                putenv($value === null ? $name : $name . '=' . trim(self::quote($value), "'"));
            }
        }

        return true;
    }

    /** @param array<string, mixed> $settings the lines store() would write for them, for an operator to copy */
    public function contentsFor(array $settings): string
    {
        $lines = [];
        foreach ($settings as $key => $value) {
            if ($value !== null) {
                $lines[] = strtoupper($key) . '=' . self::quote($value);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private static function quote(mixed $value): string
    {
        $text = match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) => (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_scalar($value) => (string)$value,
            default => '',
        };

        return "'" . str_replace("'", "'\\''", $text) . "'";
    }
}
