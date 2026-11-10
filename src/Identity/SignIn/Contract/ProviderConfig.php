<?php

namespace YesWiki\Identity\SignIn\Contract;

/** One entry of `sso_providers`, with the defaults applied. */
final class ProviderConfig
{
    /**
     * @param array<string, mixed>            $connection
     * @param array<string, string>           $attributes
     * @param list<array<string, string>>     $groupMapping
     * @param array<string, string>           $extraFields
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
        public readonly ?string $matchByNameUntil,
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
        $until = $raw['match_by_name_until'] ?? null;

        return new self(
            $id,
            (string)($raw['type'] ?? ''),
            $label,
            (bool)($raw['enabled'] ?? true),
            (bool)($raw['create_accounts'] ?? true),
            (bool)($raw['match_by_email'] ?? false),
            self::asArray($raw['connection'] ?? null),
            array_merge($defaultAttributes, self::asArray($raw['attributes'] ?? null)),
            array_values(self::asArray($raw['group_mapping'] ?? null)),
            self::asArray($raw['extra_fields'] ?? null),
            is_string($template) && $template !== '' ? $template : null,
            is_string($until) && $until !== '' ? $until : null,
        );
    }

    public function connection(string $key, mixed $default = null): mixed
    {
        return $this->connection[$key] ?? $default;
    }

    /** A directory that came from an earlier version, still allowed to match accounts by name. */
    public function isUpgradedDirectory(): bool
    {
        return $this->matchByNameUntil !== null;
    }

    /** @return array<array-key, mixed> */
    private static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
