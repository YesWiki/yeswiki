<?php

namespace YesWiki\Identity\SignIn\Contract;

/** One entry of `sso_providers`, with the defaults applied. */
final class ProviderConfig
{
    /**
     * @param array<string, mixed>        $connection
     * @param array<string, string>       $attributes
     * @param list<array<string, string>> $groupMapping
     * @param array<string, string>       $extraFields
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $label,
        public readonly bool $enabled,
        public readonly bool $createAccounts,
        public readonly bool $matchByEmail,
        public readonly array $connection,
        public readonly array $attributes,
        public readonly array $groupMapping,
        public readonly array $extraFields,
        public readonly ?string $accountNameTemplate,
        public readonly bool $matchByName,
        public readonly bool $accountNamePascalCase = true,
    ) {
    }

    /**
     * @param array<string, mixed>  $raw               the entry as written in the configuration
     * @param array<string, string> $defaultAttributes the attribute names the provider type expects
     */
    public static function fromArray(string $id, array $raw, array $defaultAttributes = []): self
    {
        $label = isset($raw['label']) && is_string($raw['label']) && $raw['label'] !== '' ? $raw['label'] : $id;
        $template = $raw['account_name_template'] ?? null;

        return new self(
            $id,
            (string)($raw['type'] ?? ''),
            $label,
            self::flag($raw['enabled'] ?? null, true),
            self::flag($raw['create_accounts'] ?? null, true),
            self::flag($raw['match_by_email'] ?? null, false),
            self::asArray($raw['connection'] ?? null),
            array_merge($defaultAttributes, self::strings($raw['attributes'] ?? null)),
            array_values(self::asArray($raw['group_mapping'] ?? null)),
            self::strings($raw['extra_fields'] ?? null),
            is_string($template) && $template !== '' ? $template : null,
            self::flag($raw['match_by_name'] ?? null, false),
            self::flag($raw['account_name_pascalcase'] ?? null, true),
        );
    }

    public function connection(string $key, mixed $default = null): mixed
    {
        return $this->connection[$key] ?? $default;
    }

    /** A flag as the administration screen reads it: anything but a yes is false. */
    private static function flag(mixed $value, bool $default): bool
    {
        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<array-key, mixed> */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * The names a hand-written section gives, by role: whatever is not a string is no name, and is left out.
     *
     * @return array<string, string>
     */
    private static function strings(mixed $value): array
    {
        $strings = [];
        foreach (self::asArray($value) as $key => $name) {
            if (is_string($name)) {
                $strings[(string)$key] = $name;
            }
        }

        return $strings;
    }
}
