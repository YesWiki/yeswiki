<?php

namespace YesWiki\Content\Entity;

/** The roles core needs a form's fields to play (ticket 11). */
class FieldRole
{
    /** The form-body key holding the explicit role => field-name map. */
    public const FORM_PROPERTY = 'field_roles';

    public const START_DATE = 'start_date';
    public const END_DATE = 'end_date';
    public const IMAGE = 'image';
    public const EMAIL = 'email';
    public const DESCRIPTION = 'description';
    public const GEOLOCATION = 'geolocation';

    /**
     * Field types that satisfy each role, most-preferred first.
     *
     * @var array<string, list<string>>
     */
    private const DEFAULT_TYPES = [
        self::START_DATE => ['listedatedeb', 'jour'],
        self::END_DATE => ['listedatefin'],
        self::IMAGE => ['image'],
        self::EMAIL => ['champs_mail'],
        self::DESCRIPTION => ['textelong'],

        self::GEOLOCATION => ['map', 'carte_google'],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::DEFAULT_TYPES);
    }

    public static function isKnown(?string $role): bool
    {
        return isset(self::DEFAULT_TYPES[(string)$role]);
    }

    /**
     * The field types that can play this role -- used both to resolve a role with no explicit mapping and to reject an explicit mapping to an incompatible field.
     *
     * @return list<string>
     */
    public static function compatibleTypes(?string $role): array
    {
        return self::DEFAULT_TYPES[(string)$role] ?? [];
    }

    /**
     * Normalize a submitted role map: unknown roles dropped, blank field names dropped, and no two roles pointing at the same field for the date pair (an event whose start and end are the same field is a data-entry mistake, not a configuration).
     *
     * @param mixed $submitted whatever the form body or a POST carried
     *
     * @return array<string, string>
     */
    public static function normalizeMap($submitted): array
    {
        if (!is_array($submitted)) {
            return [];
        }

        $map = [];
        foreach ($submitted as $role => $fieldName) {
            if (!self::isKnown((string)$role) || !is_string($fieldName)) {
                continue;
            }
            $fieldName = trim($fieldName);
            if ($fieldName !== '') {
                $map[(string)$role] = $fieldName;
            }
        }

        if (isset($map[self::START_DATE], $map[self::END_DATE])
            && $map[self::START_DATE] === $map[self::END_DATE]) {
            unset($map[self::END_DATE]);
        }

        return $map;
    }
}
