<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\ProgramFiles;

/**
 * What the Program says it needs, read from the one file that already says it (ADR-0026).
 *
 * `composer.json` is the source of truth: `make binary-check` reads it to assert a built binary
 * carries every extension it names, and `Package::phpVersionFromComposer()` reads it for the PHP
 * version. A hand-written third copy would rot within a release -- `MINIMUM_PHP_VERSION_FOR_CORE`
 * was a second copy and had already drifted to `8.2.0` against `php ^8.3`.
 */
class ComposerManifest
{
    private ProgramFiles $programFiles;

    /** @var array<string, mixed>|null */
    private ?array $manifest = null;

    public function __construct(ProgramFiles $programFiles)
    {
        $this->programFiles = $programFiles;
    }

    /** The `require.php` constraint as written, e.g. `^8.3`. */
    public function phpConstraint(): string
    {
        $require = $this->section('require');

        return is_string($require['php'] ?? null) ? $require['php'] : '';
    }

    /** The lowest PHP the constraint accepts, as a comparable version, or '' when it states none. */
    public function minimumPhpVersion(): string
    {
        $matches = [];
        if (preg_match('/^(\^|>=|>)?(\d+)(?:\.(\d+|\*))?(?:\.(\d+|\*))?/', $this->phpConstraint(), $matches) !== 1) {
            return '';
        }

        $minor = ($matches[3] ?? '0') === '*' ? '0' : ($matches[3] ?? '0');
        $patch = ($matches[4] ?? '0') === '*' ? '0' : ($matches[4] ?? '0');

        return $matches[2] . '.' . $minor . '.' . $patch;
    }

    /**
     * The extensions a wiki cannot run without.
     *
     * @return list<string> bare names: `gd`, not `ext-gd`
     */
    public function requiredExtensions(): array
    {
        return $this->extensionsIn($this->section('require'));
    }

    /**
     * The optional extensions and what each one buys, which is the sentence the Health screen wants.
     *
     * @return array<string, string> bare name => its consequence, as composer.json states it
     */
    public function suggestedExtensions(): array
    {
        $suggested = [];
        foreach ($this->section('suggest') as $package => $reason) {
            if (str_starts_with((string)$package, 'ext-') && is_string($reason)) {
                $suggested[substr((string)$package, 4)] = $reason;
            }
        }

        return $suggested;
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return list<string>
     */
    private function extensionsIn(array $section): array
    {
        $extensions = [];
        foreach (array_keys($section) as $package) {
            if (str_starts_with((string)$package, 'ext-')) {
                $extensions[] = substr((string)$package, 4);
            }
        }

        return $extensions;
    }

    /**
     * @return array<string, mixed>
     */
    private function section(string $name): array
    {
        $this->manifest ??= $this->read();
        $section = $this->manifest[$name] ?? [];

        return is_array($section) ? $section : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $decoded = json_decode($this->programFiles->read('composer.json'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
