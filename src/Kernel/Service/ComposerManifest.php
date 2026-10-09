<?php

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\ProgramFiles;

/** What the Program says it needs, read from composer.json and composer.lock (ADR-0026). */
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

    /** @return list<string> the extensions a wiki cannot run without, as bare names: `gd`, not `ext-gd` */
    public function requiredExtensions(): array
    {
        return $this->extensionsIn('require');
    }

    /** @return array<string, string> each optional extension's bare name => what it buys, as composer.json states it */
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

    /** @return array<string, string|null> each package composer.lock pins that vendor/ lacks => the version installed, null when missing */
    public function packagesOutOfStep(): array
    {
        return self::outOfStep(
            $this->programFiles->read('composer.lock'),
            $this->programFiles->read('vendor/composer/installed.json')
        );
    }

    /** @return array<string, string|null> what packagesOutOfStep() finds between a composer.lock and an installed.json */
    public static function outOfStep(string $lockJson, string $installedJson): array
    {
        $lock = json_decode($lockJson, true);
        $installed = json_decode($installedJson, true);
        if (!is_array($lock) || !is_array($installed)) {
            return [];
        }

        $present = self::packageList($installed['packages'] ?? $installed);
        $outOfStep = [];
        foreach (self::packageList($lock['packages'] ?? []) as $name => $version) {
            if (($present[$name] ?? null) !== $version) {
                $outOfStep[$name] = $present[$name] ?? null;
            }
        }
        ksort($outOfStep);

        return $outOfStep;
    }

    /** @return array<string, string> name => version of each well-formed entry in a Composer package list */
    private static function packageList(mixed $packages): array
    {
        $list = [];
        foreach (is_array($packages) ? $packages : [] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                $list[$package['name']] = $package['version'];
            }
        }

        return $list;
    }

    /** @return list<string> */
    private function extensionsIn(string $sectionName): array
    {
        $extensions = [];
        foreach (array_keys($this->section($sectionName)) as $package) {
            if (str_starts_with((string)$package, 'ext-')) {
                $extensions[] = substr((string)$package, 4);
            }
        }

        return $extensions;
    }

    /** @return array<string, mixed> */
    private function section(string $name): array
    {
        $this->manifest ??= $this->read();
        $section = $this->manifest[$name] ?? [];

        return is_array($section) ? $section : [];
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        $decoded = json_decode($this->programFiles->read('composer.json'), true);

        return is_array($decoded) ? $decoded : [];
    }
}
