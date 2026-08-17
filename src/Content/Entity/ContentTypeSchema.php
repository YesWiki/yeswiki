<?php

namespace YesWiki\Content\Entity;

/** The mandatory core structure of each Content type (ticket 10). */
class ContentTypeSchema
{
    /** An ordinary bazar form: a webmaster designs the whole template. */
    public const TYPE_ENTRY = 'entry';

    public const TYPE_PAGE = 'page';
    public const TYPE_USER = 'user';
    public const TYPE_FILE = 'file';

    /** The form-body key naming which Content type a form describes. */
    public const CONTENT_TYPE = 'content_type';

    /**
     * name => [type, label, and any attribute the core structure fixes].
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LOCKED = [
        self::TYPE_PAGE => [
            'title' => ['type' => 'texte', 'label' => 'Titre'],

            'content' => ['type' => 'textelong', 'label' => '', 'syntax' => 'wiki-textarea'],
            'keywords' => ['type' => 'tags', 'label' => 'Mots clés'],
        ],
        self::TYPE_USER => [
            'username' => ['type' => 'texte', 'label' => 'Nom d’utilisateur·ice'],

            'password' => ['type' => 'mot_de_passe', 'label' => 'Mot de passe'],
            'email' => ['type' => 'champs_mail', 'label' => 'Adresse électronique'],
            'profile_picture' => ['type' => 'image', 'label' => 'Photo de profil'],
        ],
        self::TYPE_FILE => [
            'file_content' => ['type' => 'contenu_fichier', 'label' => 'Fichier'],
        ],
    ];

    /**
     * Which locked field names a Content of this type -- its `entry_title_template` (ADR-0010) when the form has never been configured.
     *
     * @var array<string, string>
     */
    private const TITLE_TEMPLATES = [
        self::TYPE_PAGE => '{{title}}',
        self::TYPE_USER => '{{username}}',
        self::TYPE_FILE => '{{original_filename}}',
    ];

    /** Form properties (ADR-0010) that only mean anything on an ordinary bazar form. */
    private const ENTRY_ONLY_PROPERTIES = ['entry_creates_user', 'entry_bookmarklet'];

    /** Whether this is one of core's own Content types rather than an ordinary bazar form. */
    public static function isBuiltIn(?string $contentType): bool
    {
        return isset(self::LOCKED[(string)$contentType]);
    }

    public static function acceptsEntryOnlyProperties(?string $contentType): bool
    {
        return !self::isBuiltIn($contentType);
    }

    /**
     * Drop the properties a Content type cannot answer for.
     *
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    public static function stripInapplicableProperties(array $body, ?string $contentType): array
    {
        if (self::acceptsEntryOnlyProperties($contentType)) {
            return $body;
        }

        foreach (self::ENTRY_ONLY_PROPERTIES as $property) {
            unset($body[$property]);
        }

        return $body;
    }

    /** The starting `entry_title_template` of a built-in type, or null for a bazar form. */
    public static function defaultTitleTemplate(?string $contentType): ?string
    {
        return self::TITLE_TEMPLATES[(string)$contentType] ?? null;
    }

    /**
     * The locked field whose value *is* the row's tag, per Content type.
     *
     * @var array<string, string>
     */
    private const TAG_MIRRORS = [
        self::TYPE_USER => 'username',
    ];

    /** The locked field of this type that restates the row's tag, or null. */
    public static function tagMirrorField(?string $contentType): ?string
    {
        return self::TAG_MIRRORS[(string)$contentType] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_ENTRY, self::TYPE_PAGE, self::TYPE_USER, self::TYPE_FILE];
    }

    public static function isKnownType(?string $contentType): bool
    {
        return in_array((string)$contentType, self::types(), true);
    }

    /**
     * The locked field names of a Content type, in their declared order.
     *
     * @return list<string>
     */
    public static function lockedFieldNames(?string $contentType): array
    {
        return array_keys(self::LOCKED[(string)$contentType] ?? []);
    }

    public static function isLocked(?string $contentType, ?string $fieldName): bool
    {
        return isset(self::LOCKED[(string)$contentType][(string)$fieldName]);
    }

    /** The field type a locked field must have, or null if the name is not locked. */
    public static function lockedFieldType(?string $contentType, ?string $fieldName): ?string
    {
        return self::LOCKED[(string)$contentType][(string)$fieldName]['type'] ?? null;
    }

    /**
     * The whole declaration of a locked field, or null if the name is not locked.
     *
     * @return array<string, string>|null
     */
    public static function lockedField(?string $contentType, ?string $fieldName): ?array
    {
        return self::LOCKED[(string)$contentType][(string)$fieldName] ?? null;
    }

    /**
     * The default field object for a locked field -- what a template gets when the field is missing entirely.
     *
     * @return array<string, string>
     */
    public static function defaultField(string $contentType, string $fieldName): array
    {
        $declared = self::LOCKED[$contentType][$fieldName] ?? [];

        return array_merge(['name' => $fieldName], $declared);
    }

    /**
     * Repair a template so it carries every locked field of its Content type, with the declared type, without disturbing anything else.
     *
     * @param array<int, array<string, mixed>> $template
     *
     * @return array<int, array<string, mixed>>
     */
    public static function enforce(array $template, ?string $contentType): array
    {
        $locked = self::LOCKED[(string)$contentType] ?? [];
        if (empty($locked)) {
            return $template;
        }

        $seen = [];
        $kept = [];
        foreach ($template as $field) {
            $name = (string)($field['name'] ?? '');
            if (isset($locked[$name])) {
                if (isset($seen[$name])) {
                    continue;
                }
                $seen[$name] = true;

                $field['type'] = $locked[$name]['type'];
            }
            $kept[] = $field;
        }

        foreach ($locked as $name => $_declared) {
            if (isset($seen[$name])) {
                continue;
            }

            $insertAt = 0;
            foreach (array_keys($locked) as $earlier) {
                if ($earlier === $name) {
                    break;
                }
                foreach ($kept as $index => $existing) {
                    if (($existing['name'] ?? '') === $earlier) {
                        $insertAt = $index + 1;
                    }
                }
            }

            array_splice($kept, $insertAt, 0, [self::defaultField((string)$contentType, $name)]);
            $seen[$name] = true;
        }

        return $kept;
    }
}
