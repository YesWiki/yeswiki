<?php

namespace YesWiki\Render\Service;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use YesWiki\Content\Service\FileManager;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Service\LanguageService;

/** The Metadata a page's layout options write, the rules each value follows, and who may change the custom CSS. */
class PageLayout
{
    public const NAVBAR = LayoutService::NAVBAR_POSITION;
    public const NONE = 'none';
    public const NAVBAR_POSITIONS = [...LayoutService::NAVBAR_POSITIONS, self::NONE];
    public const CUSTOM_CSS = 'custom_css';
    public const OG_TITLE = 'og_title';
    public const OG_DESCRIPTION = 'og_description';
    public const OG_IMAGE = 'og_image';
    public const LANG = 'lang';
    public const THEME_KEYS = ['theme', 'squelette', 'style', 'favorite_preset', 'bgimg'];
    public const KEYS = [
        self::NAVBAR,
        ...LayoutService::PAGES,
        self::LANG,
        ...self::THEME_KEYS,
        self::CUSTOM_CSS,
        self::OG_TITLE,
        self::OG_DESCRIPTION,
        self::OG_IMAGE,
    ];
    public const CSS_MAX_LENGTH = 20000;
    public const OG_TITLE_MAX_LENGTH = 200;
    public const OG_DESCRIPTION_MAX_LENGTH = 500;

    public function __construct(
        private readonly AclService $aclService,
        private readonly FileManager $fileManager,
        private readonly LanguageService $languages,
    ) {
    }

    /** Whether the logged-in user may change this page's custom CSS: an admin or the page's owner. */
    public function mayEditCss(string $tag): bool
    {
        return $this->aclService->isAdmin() || $this->aclService->isOwner($tag);
    }

    /** @param array<string, mixed> $post @return array<string, ?string> what the panel asks for, null going back to the wiki's, empty without the panel */
    public function fromForm(array $post, string $tag): array
    {
        if (($post['layout_shown'] ?? '') !== '1') {
            return [];
        }

        $wanted = [
            self::NAVBAR => self::text($post[self::NAVBAR] ?? ''),
            self::LANG => self::text($post[self::LANG] ?? ''),
            self::OG_TITLE => self::text($post[self::OG_TITLE] ?? ''),
            self::OG_DESCRIPTION => self::text($post[self::OG_DESCRIPTION] ?? ''),
            self::OG_IMAGE => self::text($post[self::OG_IMAGE] ?? ''),
        ];
        foreach (LayoutService::PAGES as $role) {
            $wanted[$role] = match ($post[$role . '_mode'] ?? '') {
                self::NONE => self::NONE,
                'page' => self::text($post[$role . '_page'] ?? ''),
                default => null,
            };
        }
        if (($post['layout_own_theme'] ?? '') === '1') {
            $wanted['theme'] = self::text($post['theme_select'] ?? '');
            $wanted['squelette'] = self::withSuffix(self::text($post['squelette_select'] ?? ''), '.tpl.html');
            $wanted['style'] = self::withSuffix(self::text($post['style_select'] ?? ''), '.css');
            $wanted['favorite_preset'] = self::withSuffix(self::text($post['preset_select'] ?? ''), '.css');
            $wanted['bgimg'] = self::text($post['bgimg'] ?? '');
        } else {
            $wanted += array_fill_keys(self::THEME_KEYS, null);
        }
        if ($this->mayEditCss($tag)) {
            $css = str_replace("\r", '', (string)($post[self::CUSTOM_CSS] ?? ''));
            $wanted[self::CUSTOM_CSS] = trim($css) === '' ? null : $css;
        }

        return $this->checked($wanted, $tag);
    }

    /** @param array<array-key, mixed> $metadata @return array<string, ?string> the keys checked, '' read as null; throws on an unknown key, a bad value or someone else's CSS */
    public function checked(array $metadata, string $tag): array
    {
        $checked = [];
        foreach ($metadata as $key => $value) {
            $key = (string)$key;
            if (!in_array($key, self::KEYS, true)) {
                throw new \InvalidArgumentException(_t('PAGE_LAYOUT_UNKNOWN_KEY', ['key' => $key]));
            }
            if ($value !== null && !is_string($value)) {
                throw new \InvalidArgumentException(_t('PAGE_LAYOUT_INVALID_VALUE', ['key' => $key]));
            }
            if ($key === self::CUSTOM_CSS && !$this->mayEditCss($tag)) {
                throw new AccessDeniedException(_t('PAGE_LAYOUT_CSS_NOT_ALLOWED'));
            }
            $value = $key === self::CUSTOM_CSS ? $value : self::text($value);
            if ($value === null || trim($value) === '') {
                $checked[$key] = null;
                continue;
            }
            if (!$this->accepts($key, $value)) {
                throw new \InvalidArgumentException($key === self::CUSTOM_CSS
                    ? _t('PAGE_LAYOUT_CSS_REFUSED')
                    : _t('PAGE_LAYOUT_INVALID_VALUE', ['key' => $key]));
            }
            $checked[$key] = $value;
        }

        return $checked;
    }

    /** @param array<string, mixed> $current @param array<string, ?string> $wanted @return array<string, ?string> only the keys whose value would change */
    public static function changes(array $current, array $wanted): array
    {
        return array_filter(
            $wanted,
            fn (?string $value, string $key): bool => ($current[$key] ?? null) !== $value,
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Whether an OpenGraph image is given as an https URL rather than a file entry's tag. */
    public static function isHttpsUrl(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false && str_starts_with(strtolower($value), 'https://');
    }

    private function accepts(string $key, string $value): bool
    {
        return match (true) {
            $key === self::NAVBAR => in_array($value, self::NAVBAR_POSITIONS, true),
            in_array($key, LayoutService::PAGES, true) => $value === self::NONE
                || preg_match('/^' . WN_CAMEL_CASE_EVOLVED . '$/u', $value) === 1,
            $key === self::LANG => in_array($value, $this->languages->availableLanguages(), true),
            $key === 'favorite_preset' => preg_match('#^(' . preg_quote(ThemeManager::CUSTOM_CSS_PRESETS_PREFIX, '#') . ')?[\w.-]+$#', $value) === 1
                && !str_contains($value, '..'),
            in_array($key, self::THEME_KEYS, true) => preg_match('/^[\w.-]+$/', $value) === 1 && !str_contains($value, '..'),
            $key === self::CUSTOM_CSS => !str_contains($value, '<') && mb_strlen($value) <= self::CSS_MAX_LENGTH,
            $key === self::OG_TITLE => mb_strlen($value) <= self::OG_TITLE_MAX_LENGTH,
            $key === self::OG_DESCRIPTION => mb_strlen($value) <= self::OG_DESCRIPTION_MAX_LENGTH,
            $key === self::OG_IMAGE => self::isHttpsUrl($value) || $this->fileManager->isFileTag($value),
            default => false,
        };
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string)$value);

        return $text === '' ? null : $text;
    }

    private static function withSuffix(?string $value, string $suffix): ?string
    {
        return $value === null || str_ends_with($value, $suffix) ? $value : $value . $suffix;
    }
}
