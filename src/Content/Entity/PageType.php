<?php

namespace YesWiki\Content\Entity;

/** What `pages.type` may hold, and what the `TYPE_URI` triples it replaced said (ticket 27). */
final class PageType
{
    /** An ordinary wiki page: the default, and what an untyped row used to be. */
    public const PAGE = ContentTypeSchema::TYPE_PAGE;
    /** A bazar entry, naming its own form in `body.form_id`. */
    public const ENTRY = ContentTypeSchema::TYPE_ENTRY;
    public const USER = ContentTypeSchema::TYPE_USER;
    public const FILE = ContentTypeSchema::TYPE_FILE;
    /** A form. */
    public const FORM = 'form';
    /** A value list, the options behind a `liste`/`checkbox` field. */
    public const LIST = 'list';
    /** A comment: a page row whose `parent` names what it comments on. */
    public const COMMENT = 'comment';

    /** The column's default, and what `save()` writes when a caller names no type. */
    public const DEFAULT = self::PAGE;

    /**
     * Legacy `TYPE_URI` triple value => the type it becomes.
     *
     * @var array<string, string>
     */
    public const BY_LEGACY_TRIPLE = [
        '' => self::PAGE,
        'fiche_bazar' => self::ENTRY,
        'user' => self::USER,
        'file' => self::FILE,
        'form' => self::FORM,
        'liste' => self::LIST,
    ];

    /**
     * @return list<string> every value the column may hold
     */
    public static function all(): array
    {
        return [self::PAGE, self::ENTRY, self::USER, self::FILE, self::FORM, self::LIST, self::COMMENT];
    }

    public static function isKnown(?string $type): bool
    {
        return $type !== null && in_array($type, self::all(), true);
    }

    /**
     * The type a legacy triple value named, or null for a triple this concept never described -- a migration marker, a list of favorites.
     */
    public static function fromLegacyTriple(?string $tripleValue): ?string
    {
        return self::BY_LEGACY_TRIPLE[(string)$tripleValue] ?? null;
    }

    /**
     * Whether a form describes rows of this type -- so `FormManager::getByContentType()` has something to answer with.
     */
    public static function isFormBacked(?string $type): bool
    {
        return ContentTypeSchema::isKnownType($type);
    }
}
