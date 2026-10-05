<?php

namespace YesWiki\Kernel\Entity;

/** What an extension says about itself in its `composer.json` (ADR-0029); its folder name is its identity. */
final class ExtensionManifest
{
    public const FILENAME = 'composer.json';

    /** @param array<string, mixed> $data */
    private function __construct(public readonly string $folder, private readonly array $data)
    {
    }

    /** The manifest of the extension in $path, empty when it has none or it does not parse. */
    public static function of(string $folder, string $path): self
    {
        $file = rtrim($path, '/') . '/' . self::FILENAME;
        $decoded = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;

        return new self($folder, is_array($decoded) ? $decoded : []);
    }

    /** The release it was published as, injected from its tag; null for a checkout, which has none. */
    public function version(): ?string
    {
        $version = $this->data['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function label(string $language): string
    {
        return $this->translated('label', $language) ?? $this->folder;
    }

    public function description(string $language): string
    {
        $fallback = $this->data['description'] ?? '';

        return $this->translated('description', $language) ?? (is_string($fallback) ? $fallback : '');
    }

    /**
     * What it needs of PHP itself: `php` and `ext-*` entries of `require`.
     *
     * @return array<string, string>
     */
    public function platformRequirements(): array
    {
        $require = is_array($this->data['require'] ?? null) ? $this->data['require'] : [];

        $platform = [];
        foreach ($require as $package => $constraint) {
            if (is_string($package) && is_string($constraint) && ($package === 'php' || str_starts_with($package, 'ext-'))) {
                $platform[$package] = $constraint;
            }
        }

        return $platform;
    }

    /** @return list<string> the other extensions it needs active, by folder name */
    public function requiredExtensions(): array
    {
        $required = $this->yeswiki()['requires-extensions'] ?? [];

        return is_array($required) ? array_values(array_filter($required, 'is_string')) : [];
    }

    /**
     * What stops it from running here, each as a short sentence; empty when nothing does.
     *
     * @param list<string> $activeExtensions
     *
     * @return list<string>
     */
    public function unmetRequirements(array $activeExtensions): array
    {
        $unmet = [];
        foreach ($this->platformRequirements() as $package => $constraint) {
            if ($package === 'php' && !PlatformConstraint::allows($constraint, PlatformConstraint::phpVersion())) {
                $unmet[] = "PHP {$constraint} (this is " . PlatformConstraint::phpVersion() . ')';
            } elseif ($package !== 'php' && !extension_loaded(substr($package, 4))) {
                $unmet[] = "PHP extension {$package}";
            }
        }
        foreach ($this->requiredExtensions() as $extension) {
            if (!in_array($extension, $activeExtensions, true)) {
                $unmet[] = "extension {$extension} active";
            }
        }

        return $unmet;
    }

    private function translated(string $key, string $language): ?string
    {
        $values = $this->yeswiki()[$key] ?? null;
        if (is_string($values) && $values !== '') {
            return $values;
        }
        if (!is_array($values)) {
            return null;
        }
        foreach ([$language, 'en'] as $candidate) {
            if (is_string($values[$candidate] ?? null) && $values[$candidate] !== '') {
                return $values[$candidate];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function yeswiki(): array
    {
        $extra = is_array($this->data['extra'] ?? null) ? $this->data['extra'] : [];

        return is_array($extra['yeswiki'] ?? null) ? $extra['yeswiki'] : [];
    }
}
