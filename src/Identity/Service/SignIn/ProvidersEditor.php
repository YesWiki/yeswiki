<?php

namespace YesWiki\Identity\Service\SignIn;

use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/**
 * Writes the `sso_providers` section of the configuration file for the administration screen (spec 5.5, 5.6, 7, 8.3).
 * The file is read and written whole on every call: nothing is kept between calls, and what the screen does not show survives a save.
 */
class ProvidersEditor
{
    /** The attribute names the screen offers, whatever the type. */
    public const ATTRIBUTES = ['id', 'email', 'first_name', 'last_name', 'groups'];

    private const NOT_AN_INPUT = ['divider', 'hint'];

    public function __construct(
        private readonly ConfigurationService $configurationService,
        private readonly ProviderRegistry $registry,
        private readonly AccountLinks $links,
    ) {
    }

    /**
     * The entries as the file holds them, in sign-in page order. With no section, local is there: it is enabled, and the first save writes it.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $section = $this->load()[ProviderRegistry::SECTION] ?? [];
        $entries = [];
        if (is_array($section)) {
            foreach ($section as $id => $entry) {
                if (is_array($entry)) {
                    $entries[(string)$id] = $entry;
                }
            }
        }
        if ($entries === []) {
            $entries[LocalType::NAME] = ['type' => LocalType::NAME, 'enabled' => true];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $posted
     *
     * @return string|list<string> the identifier of the new provider, or what is wrong
     */
    public function create(string $type, array $posted): string|array
    {
        $types = $this->registry->types();
        if (!isset($types[$type])) {
            return [_t('SIGN_IN_ADMIN_UNKNOWN_TYPE')];
        }
        if ($type === LocalType::NAME) {
            return [_t('SIGN_IN_ADMIN_LOCAL_EXISTS')];
        }

        $entries = $this->all();
        [$entry, $errors] = $this->apply(['type' => $type], $types[$type]->connectionSettings(), $posted);
        if ($errors !== []) {
            return $errors;
        }
        $id = $this->freeIdentifier((string)$entry['label'], $type, $entries);
        $entries[$id] = $entry;

        return $this->save($entries) ? $id : [_t('SIGN_IN_ADMIN_NOT_WRITABLE')];
    }

    /**
     * @param array<string, mixed> $posted
     *
     * @return list<string> what is wrong; empty when saved
     */
    public function update(string $id, array $posted): array
    {
        $entries = $this->all();
        if (!isset($entries[$id])) {
            return [_t('SIGN_IN_ADMIN_UNKNOWN_PROVIDER')];
        }
        $type = $this->registry->types()[(string)($entries[$id]['type'] ?? '')] ?? null;
        if ($type === null) {
            return [_t('SIGN_IN_ADMIN_UNAVAILABLE_NOT_EDITABLE')];
        }
        // the type is fixed once created, and so is the entry's place in the section.
        unset($posted['type']);

        [$entry, $errors] = $this->apply($entries[$id], $type->connectionSettings(), $posted);
        if ($errors !== []) {
            return $errors;
        }
        $entries[$id] = $entry;
        if (!self::flag($entry['enabled']) && !$this->hasEnabledProvider($entries)) {
            return [_t('SIGN_IN_ADMIN_LAST_ENABLED')];
        }

        return $this->save($entries) ? [] : [_t('SIGN_IN_ADMIN_NOT_WRITABLE')];
    }

    /** @return string|null why not; null when done */
    public function setEnabled(string $id, bool $on): ?string
    {
        $entries = $this->all();
        if (!isset($entries[$id])) {
            return _t('SIGN_IN_ADMIN_UNKNOWN_PROVIDER');
        }
        if ($on && !isset($this->registry->types()[(string)($entries[$id]['type'] ?? '')])) {
            return _t('SIGN_IN_ADMIN_UNAVAILABLE_NOT_EDITABLE');
        }
        $entries[$id]['enabled'] = $on;
        if (!$on && !$this->hasEnabledProvider($entries)) {
            return _t('SIGN_IN_ADMIN_LAST_ENABLED');
        }

        return $this->save($entries) ? null : _t('SIGN_IN_ADMIN_NOT_WRITABLE');
    }

    /** @return string|null why not; null when done */
    public function delete(string $id): ?string
    {
        $entries = $this->all();
        if (!isset($entries[$id])) {
            return _t('SIGN_IN_ADMIN_UNKNOWN_PROVIDER');
        }
        if ($id === LocalType::NAME || ($entries[$id]['type'] ?? null) === LocalType::NAME) {
            return _t('SIGN_IN_ADMIN_LOCAL_STAYS');
        }
        if (($this->links->countByProvider()[$id] ?? 0) > 0) {
            return _t('SIGN_IN_ADMIN_HAS_ACCOUNTS');
        }
        unset($entries[$id]);
        if (!$this->hasEnabledProvider($entries)) {
            return _t('SIGN_IN_ADMIN_LAST_ENABLED');
        }

        return $this->save($entries) ? null : _t('SIGN_IN_ADMIN_NOT_WRITABLE');
    }

    /**
     * What a posted form changes of an entry: only the keys the screen shows.
     *
     * @param array<string, mixed>  $entry
     * @param list<Setting>         $settings
     * @param array<string, mixed>  $posted
     *
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function apply(array $entry, array $settings, array $posted): array
    {
        $errors = [];

        if (array_key_exists('label', $posted) || !isset($entry['label'])) {
            $label = trim((string)($posted['label'] ?? ''));
            if ($label === '') {
                $errors[] = _t('SIGN_IN_ADMIN_LABEL_REQUIRED');
            }
            $entry['label'] = $label;
        }
        if (array_key_exists('enabled', $posted)) {
            $entry['enabled'] = self::flag($posted['enabled']);
        } elseif (!isset($entry['enabled'])) {
            $entry['enabled'] = true;
        }

        $postedConnection = is_array($posted['connection'] ?? null) ? $posted['connection'] : [];
        $connection = is_array($entry['connection'] ?? null) ? $entry['connection'] : [];
        foreach ($settings as $setting) {
            $type = $setting->type();
            $name = $setting->name();
            if ($type === null || in_array($type, self::NOT_AN_INPUT, true)) {
                continue;
            }
            $declared = $setting->toArray();
            if (array_key_exists($name, $postedConnection)) {
                $value = $postedConnection[$name];
                if ($type === 'checkbox') {
                    $connection[$name] = self::flag($value);
                } elseif ($type === 'secret') {
                    if (is_string($value) && $value !== '') {
                        $connection[$name] = $value;
                    }
                } else {
                    $value = is_string($value) ? trim($value) : '';
                    if ($value === '') {
                        unset($connection[$name]);
                    } elseif ($type === 'url' && !self::isWebAddress($value)) {
                        $errors[] = _t('SIGN_IN_ADMIN_NOT_A_URL', ['setting' => (string)($declared['label'] ?? $name)]);
                    } else {
                        $connection[$name] = $value;
                    }
                }
            }
            $filled = isset($connection[$name]) && $connection[$name] !== '';
            if (!empty($declared['required']) && !$filled && $type !== 'checkbox') {
                $errors[] = _t('SIGN_IN_ADMIN_REQUIRED', ['setting' => (string)($declared['label'] ?? $name)]);
            }
        }
        if ($settings !== [] || $connection !== [] || isset($entry['connection'])) {
            $entry['connection'] = $connection;
        }

        if (is_array($posted['attributes'] ?? null)) {
            $attributes = is_array($entry['attributes'] ?? null) ? $entry['attributes'] : [];
            foreach (self::ATTRIBUTES as $name) {
                if (!array_key_exists($name, $posted['attributes'])) {
                    continue;
                }
                $value = trim((string)$posted['attributes'][$name]);
                if ($value === '') {
                    unset($attributes[$name]);
                } else {
                    $attributes[$name] = $value;
                }
            }
            $entry['attributes'] = $attributes;
        }

        foreach (['create_accounts', 'match_by_email'] as $flag) {
            if (array_key_exists($flag, $posted)) {
                $entry[$flag] = self::flag($posted[$flag]);
            }
        }

        if (is_array($posted['group_mapping'] ?? null)) {
            $rows = [];
            foreach ($posted['group_mapping'] as $row) {
                $provider = is_array($row) ? trim((string)($row['provider'] ?? '')) : '';
                $wiki = is_array($row) ? trim((string)($row['wiki'] ?? '')) : '';
                if ($provider !== '' && $wiki !== '') {
                    $rows[] = ['provider' => $provider, 'wiki' => $wiki];
                }
            }
            $entry['group_mapping'] = $rows;
        }

        if (is_array($posted['extra_fields'] ?? null)) {
            $extra = [];
            foreach ($posted['extra_fields'] as $row) {
                $field = is_array($row) ? trim((string)($row['field'] ?? '')) : '';
                $attribute = is_array($row) ? trim((string)($row['attribute'] ?? '')) : '';
                if ($field !== '' && $attribute !== '') {
                    $extra[$field] = $attribute;
                }
            }
            $entry['extra_fields'] = $extra;
        }

        return [$entry, $errors];
    }

    /** At least one entry is on, and its type is installed. */
    private function hasEnabledProvider(array $entries): bool
    {
        $types = $this->registry->types();
        foreach ($entries as $entry) {
            if (self::flag($entry['enabled'] ?? true) && isset($types[(string)($entry['type'] ?? '')])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, array<string, mixed>> $entries */
    private function freeIdentifier(string $label, string $type, array $entries): string
    {
        $slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', self::asciiOf($label)), '-'));
        $base = $slug === '' ? $type : $slug;
        $id = $base;
        for ($n = 2; isset($entries[$id]); ++$n) {
            $id = $base . '-' . $n;
        }

        return $id;
    }

    private static function asciiOf(string $text): string
    {
        $converted = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : false;

        return is_string($converted) ? $converted : $text;
    }

    private static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function isWebAddress(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /** @param array<string, array<string, mixed>> $entries */
    private function save(array $entries): bool
    {
        $configuration = $this->load();
        $configuration[ProviderRegistry::SECTION] = $entries;

        return (bool)$this->configurationService->write($configuration);
    }

    private function load(): \YesWiki\Kernel\Entity\ConfigurationFile
    {
        $configuration = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $configuration->load();

        return $configuration;
    }
}
