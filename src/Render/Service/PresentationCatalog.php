<?php

namespace YesWiki\Render\Service;

use YesWiki\Content\Entity\FieldRole;
use YesWiki\Content\Service\FieldRoleResolver;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Render\Entity\Presentation;

/** Every list template the wiki can draw, each read from the `{# presentation … #}` header of its own file (docs/en/dev.md). */
class PresentationCatalog
{
    public const SHARED_DIR = 'templates/presentations';
    public const DYNAMIC_DIR = 'templates/entries/index-dynamic-templates';
    public const CUSTOM_PREFIX = 'custom/templates/core/';

    /** Templates outside the two folders that still cannot do without a role (ticket 11). */
    private const LEGACY_REQUIREMENTS = [
        'agenda' => [FieldRole::START_DATE],
        'gogocarto' => [FieldRole::GEOLOCATION],
        'gogomap' => [FieldRole::GEOLOCATION],
    ];

    /** @var array<string, Presentation>|null name => presentation, in switcher order */
    private ?array $presentations = null;

    public function __construct(
        private readonly ProgramFiles $programFiles,
        private readonly Storage $storage,
        private readonly FieldRoleResolver $roles,
    ) {
    }

    /** @return list<Presentation> */
    public function all(): array
    {
        return array_values($this->load());
    }

    public function get(string $template): ?Presentation
    {
        return $this->load()[self::bare($template)] ?? null;
    }

    public function has(string $template): bool
    {
        return $this->get($template) !== null;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<Presentation> the ones whose requirements this form meets
     */
    public function fitting(array $form): array
    {
        return array_values(array_filter(
            $this->load(),
            fn (Presentation $presentation) => $this->roles->hasRoles($form, ...$presentation->requires)
        ));
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return list<array{name: string, label: string, icon: string, presentations: list<array<string, mixed>>}>
     */
    public function switcherFor(array $form): array
    {
        return $this->group($this->fitting($form));
    }

    /**
     * @param list<Presentation> $presentations
     *
     * @return list<array{name: string, label: string, icon: string, presentations: list<array<string, mixed>>}>
     */
    private function group(array $presentations): array
    {
        $groups = [];
        foreach ($presentations as $presentation) {
            $groups[$presentation->category][] = $presentation->toArray();
        }

        $switcher = [];
        foreach (array_keys(Presentation::CATEGORIES) as $category) {
            if (empty($groups[$category])) {
                continue;
            }
            $switcher[] = [
                'name' => $category,
                'label' => Presentation::categoryLabel($category),
                'icon' => Presentation::categoryIcon($category),
                'presentations' => $groups[$category],
            ];
        }

        return $switcher;
    }

    /**
     * The shared shapes grouped for a switcher over Items that are not a form's: the global search.
     *
     * @return list<array{name: string, label: string, icon: string, presentations: list<array<string, mixed>>}>
     */
    public function sharedSwitcher(): array
    {
        return $this->group(array_values(array_filter($this->load(), fn (Presentation $p) => $p->shared)));
    }

    /** @return list<string> the roles a template cannot draw without */
    public function requiredRoles(string $template): array
    {
        $name = self::bare($template);
        $presentation = $this->get($name);
        if ($presentation !== null) {
            return $presentation->requires;
        }

        return self::LEGACY_REQUIREMENTS[$name] ?? [];
    }

    /** @return array<string, Presentation> */
    private function load(): array
    {
        if ($this->presentations !== null) {
            return $this->presentations;
        }

        $found = [];
        foreach ($this->files(self::SHARED_DIR) as $name => [$contents, $custom]) {
            if (PresentationRenderer::knows($name)) {
                $found[$name] = $this->describe($name, $contents, true, $custom);
            }
        }
        foreach ($this->files(self::DYNAMIC_DIR) as $name => [$contents, $custom]) {
            if (!isset($found[$name])) {
                $found[$name] = $this->describe($name, $contents, false, $custom);
            }
        }

        return $this->presentations = $found;
    }

    /** @return array<string, array{0: string, 1: bool}> name => [contents, from custom/], the wiki's copy winning */
    private function files(string $directory): array
    {
        $files = [];
        foreach ($this->programFiles->files($directory) as $path) {
            $name = self::nameOf($path);
            if ($name !== null) {
                $files[$name] = [$this->programFiles->read($path), false];
            }
        }
        $customDirectory = self::CUSTOM_PREFIX . substr($directory, \strlen('templates/'));
        foreach ($this->storage->files($customDirectory) as $path) {
            $name = self::nameOf($path);
            if ($name !== null) {
                $files[$name] = [$this->storage->read($path), true];
            }
        }
        ksort($files);

        return $files;
    }

    /** A template's name from its path, or null for a partial or anything that is not a plain `.twig` file. */
    private static function nameOf(string $path): ?string
    {
        $file = basename($path);
        if (!preg_match('/^([a-z0-9][a-z0-9_-]*)\.twig$/i', $file, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function describe(string $name, string $contents, bool $shared, bool $custom): Presentation
    {
        $header = self::header($contents);
        $category = strtolower($header['category'] ?? '');
        if (!isset(Presentation::CATEGORIES[$category])) {
            $category = Presentation::DEFAULT_CATEGORY;
        }
        $requires = array_values(array_filter(array_map('trim', explode(',', $header['requires'] ?? ''))));

        return new Presentation(
            $name,
            isset($header['label']) && $header['label'] !== '' ? _t($header['label']) : $name,
            $header['icon'] ?? Presentation::categoryIcon($category),
            $category,
            $requires,
            $shared,
            $custom,
        );
    }

    /** @return array<string, string> the `key: value` lines of the leading `{# presentation … #}` comment */
    private static function header(string $contents): array
    {
        if (!preg_match('/\A\s*\{#\s*presentation\b(.*?)#\}/s', substr($contents, 0, 2048), $comment)) {
            return [];
        }
        $header = [];
        foreach (preg_split('/\R/', $comment[1]) ?: [] as $line) {
            if (preg_match('/^\s*([a-z]+)\s*:\s*(.*?)\s*$/', $line, $pair)) {
                $header[$pair[1]] = $pair[2];
            }
        }

        return $header;
    }

    private static function bare(string $template): string
    {
        return (string)preg_replace('/\.(twig|tpl\.html)$/', '', basename($template));
    }
}
