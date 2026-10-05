<?php

namespace YesWiki\Render\Service;

use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\DoryphoreLeftovers;

/** Brings a Doryphore wiki's presets, fonts and images back from private/doryphore, and makes its presets complete. */
class DoryphoreLookRestorer
{
    /** The look folders worth bringing back to a wiki that is already running on Ectoplasme. */
    private const RESTORED = ['css-presets/', 'themes/', 'fonts/', 'images/'];

    public function __construct(
        private readonly Storage $storage,
        private readonly PresetUpgrader $upgrader,
    ) {
    }

    /**
     * Restore what custom/ lacks, then upgrade every preset still in an old vocabulary.
     *
     * @return array{restored: list<string>, upgraded: list<array{path: string, missing: list<string>, localised: list<string>, imports: list<string>}>}
     */
    public function restore(string $instanceDir, bool $colouredNavbar): array
    {
        $restored = [];
        foreach (DoryphoreLeftovers::lookIn($instanceDir . '/' . DoryphoreLeftovers::ASIDE . '/custom') as $relative => $file) {
            if (!$this->isRestored($relative)) {
                continue;
            }
            $target = 'custom/' . $relative;
            if ($this->storage->fileExists($target)) {
                continue;
            }
            $this->storage->writeFrom($target, $file);
            $restored[] = $target;
        }

        return ['restored' => $restored, 'upgraded' => $this->upgradeAll($colouredNavbar)];
    }

    /**
     * Rewrite every instance preset that is not a complete Preset yet.
     *
     * @return list<array{path: string, missing: list<string>, localised: list<string>, imports: list<string>}>
     */
    public function upgradeAll(bool $colouredNavbar): array
    {
        $upgraded = [];
        foreach ($this->presetFiles() as $path) {
            $css = $this->storage->read($path);
            if (!$this->upgrader->needsUpgrade($css)) {
                continue;
            }
            $result = $this->upgrader->upgrade($css, $path, $colouredNavbar);
            $this->storage->write($path, $result['css']);
            $upgraded[] = ['path' => $path] + array_diff_key($result, ['css' => true]);
        }

        return $upgraded;
    }

    /**
     * One line per upgraded preset, for a migration to say.
     *
     * @param list<array{path: string, missing: list<string>, localised: list<string>, imports: list<string>}> $upgraded
     */
    public static function summary(array $upgraded): string
    {
        $lines = [];
        foreach ($upgraded as $preset) {
            $line = basename($preset['path']);
            if ($preset['missing'] !== []) {
                $line .= ' (' . count($preset['missing']) . ' tokens left to fill in)';
            }
            if ($preset['localised'] !== []) {
                $line .= ', fonts now served by the wiki: ' . implode(', ', $preset['localised']);
            }
            if ($preset['imports'] !== []) {
                $line .= ', still loaded from elsewhere because they could not be downloaded: ' . implode(' ', $preset['imports']);
            }
            $lines[] = $line;
        }

        return implode('; ', $lines);
    }

    /** Whether a preset id from the configuration names a file this wiki has. */
    public function resolves(string $presetId): bool
    {
        if (!str_starts_with($presetId, ThemeManager::CUSTOM_CSS_PRESETS_PREFIX)) {
            return true;
        }

        return $this->storage->fileExists(
            ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/' . substr($presetId, strlen(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX))
        );
    }

    /**
     * Every preset stylesheet the instance holds.
     *
     * @return list<string>
     */
    private function presetFiles(): array
    {
        $paths = [];
        foreach ([ThemeManager::CUSTOM_CSS_PRESETS_PATH . '/*.css', 'custom/themes/*/presets/*.css'] as $pattern) {
            foreach ($this->storage->glob($pattern) as $path) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function isRestored(string $relative): bool
    {
        foreach (self::RESTORED as $folder) {
            if (str_starts_with($relative, $folder)) {
                return true;
            }
        }

        return false;
    }
}
