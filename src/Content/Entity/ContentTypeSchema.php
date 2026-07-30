<?php

namespace YesWiki\Content\Entity;

/**
 * The mandatory core structure of each Content type (ticket 10).
 *
 * Page, User and File are forms like any other: their fields live in an ordinary form
 * template, are edited in the ordinary designer, and sit in one list beside whatever
 * fields a webmaster adds. What makes the core ones different is only that they cannot
 * be **deleted** or **retyped** -- everything else about them (label, help text, order,
 * Field ACL) is the webmaster's to change.
 *
 * Locked-ness is declared here, in code, and deliberately **not stored as an attribute
 * on the field object**. If it were stored, every write vector the ticket asks us to
 * guard -- the designer, the API, CSV import, form duplication, a hand-edited template --
 * would also be a way to clear the flag. Because the source of truth is this class, a
 * template that arrives without a locked field is repaired rather than rejected: the
 * field comes back, and the webmaster's edits to the rest of the template stand.
 */
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
     * `type` is the only attribute enforced on write. The labels here are defaults for
     * a form that has never been designed; once a webmaster relabels a field, their
     * label is what survives.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LOCKED = [
        self::TYPE_PAGE => [
            // a page's own title, distinct from its tag
            'title' => ['type' => 'texte', 'label' => 'Titre'],
            // the wiki markup -- `content` is where ticket 09 put it
            'content' => ['type' => 'textelong', 'label' => 'Contenu'],
            'keywords' => ['type' => 'tags', 'label' => 'Mots clés'],
        ],
        self::TYPE_USER => [
            // the account name is the Content's tag, the way an entry's `tag` is:
            // the field exists so the designer can label and order it, not so it can
            // be typed into freely
            'username' => ['type' => 'texte', 'label' => 'Nom d’utilisateur·ice'],
            // stays in the versioned body under Field ACL -- ADR-0003, unchanged
            'password' => ['type' => 'mot_de_passe', 'label' => 'Mot de passe'],
            'email' => ['type' => 'champs_mail', 'label' => 'Adresse électronique'],
        ],
        self::TYPE_FILE => [
            // what the visitor uploaded, and what is actually on disk: the pair the
            // file service needs to serve a download at all
            'original_filename' => ['type' => 'texte', 'label' => 'Nom du fichier'],
            'stored_filename' => ['type' => 'texte', 'label' => 'Fichier stocké'],
            // the owning-page association, which seeds the file's read ACL
            'uploaded_from' => ['type' => 'texte', 'label' => 'Page d’origine'],
        ],
    ];

    /**
     * Which locked field names a Content of this type -- its `entry_title_template`
     * (ADR-0010) when the form has never been configured.
     *
     * An ordinary bazar form falls back to the historical `{{bf_titre}}` convention because
     * that is the field its webmaster is likely to have; a built-in type has no such doubt,
     * since the naming field is one of its own locked fields.
     *
     * @var array<string, string>
     */
    private const TITLE_TEMPLATES = [
        self::TYPE_PAGE => '{{title}}',
        self::TYPE_USER => '{{username}}',
        self::TYPE_FILE => '{{original_filename}}',
    ];

    /**
     * Form properties (ADR-0010) that only mean anything on an ordinary bazar form.
     *
     * "Submitting an entry creates a user account" and "install a bookmarklet that files
     * a web page as an entry" describe visitor submissions to a webmaster's form. A page,
     * an account and an uploaded file are not submitted that way -- the User form *is*
     * the accounts -- so these are not options a built-in type gets to have wrong.
     */
    private const ENTRY_ONLY_PROPERTIES = ['entry_creates_user', 'entry_bookmarklet'];

    /**
     * Whether this is one of core's own Content types rather than an ordinary bazar form.
     *
     * A built-in form is part of the wiki's machinery: delete the Page form and pages stop
     * having a schema, an editor and a list. Callers use this to refuse the destructive
     * operations and to tell system content from a webmaster's own in the UI.
     */
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
     * Applied on read as well as on write, for the same reason locked fields are: a body
     * can arrive carrying one by a route that never came through this service, and a form
     * that presents an option it will not honour is worse than one that hides it.
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

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_ENTRY, self::TYPE_PAGE, self::TYPE_USER, self::TYPE_FILE];
    }

    public static function isKnownType(?string $contentType): bool
    {
        return in_array((string)$contentType, self::types(), true);
    }

    /**
     * The locked field names of a Content type, in their declared order. Empty for an
     * ordinary bazar form, which has no core structure.
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
     * The default field object for a locked field -- what a template gets when the
     * field is missing entirely.
     *
     * @return array<string, string>
     */
    public static function defaultField(string $contentType, string $fieldName): array
    {
        $declared = self::LOCKED[$contentType][$fieldName] ?? [];

        return array_merge(['name' => $fieldName], $declared);
    }

    /**
     * Repair a template so it carries every locked field of its Content type, with the
     * declared type, without disturbing anything else.
     *
     * Order matters here: a locked field the webmaster moved keeps its position, and a
     * missing one is prepended in declared order rather than appended, so a repaired
     * Page template reads title / content / keywords / <the webmaster's fields> rather
     * than burying the core structure at the bottom.
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
                    // a duplicate of a locked field: the first one wins
                    continue;
                }
                $seen[$name] = true;
                // the type is the one attribute the webmaster does not get to change
                $field['type'] = $locked[$name]['type'];
            }
            $kept[] = $field;
        }

        $missing = [];
        foreach ($locked as $name => $_declared) {
            if (!isset($seen[$name])) {
                $missing[] = self::defaultField((string)$contentType, $name);
            }
        }

        return array_merge($missing, $kept);
    }
}
