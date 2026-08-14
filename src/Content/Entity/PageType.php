<?php

namespace YesWiki\Content\Entity;

/**
 * What `pages.type` may hold, and what the `TYPE_URI` triples it replaced said (ticket 27).
 *
 * Every row in `pages` is one of seven kinds. Before this ticket that fact lived in the
 * `triples` table, one row per typed Content, and "untyped means page" was a rule every
 * caller had to know. It cost two to four uncached `SELECT id FROM triples` per distinct tag
 * on every page render -- {@see \YesWiki\Kernel\Service\TripleStore::exist()} consults
 * neither cache -- and a `LEFT JOIN triples` in every query that needed the type.
 *
 * ## Why these spellings
 *
 * They are {@see ContentTypeSchema}'s, which `search_index.content_type` already stored: one
 * vocabulary end to end, and two translation maps deleted. Two stored values changed name on
 * the way into the column, because the column is core's own and no longer user data:
 *
 * | triple value | `pages.type` |
 * |---|---|
 * | *(no triple)* | `page` -- stated, not inferred from absence |
 * | `fiche_bazar` | `entry` |
 * | `liste` | `list` |
 *
 * **`liste` is also a field type name** (`SelectListField`), and that one is stored user data
 * and stays French. The rename here is the Content type only; a sweep that conflates the two
 * breaks every select-list field in every form.
 *
 * ## What stays in `triples`
 *
 * Only the six values below ever moved. Migration markers (the resource is a file name, not
 * a page), `SOURCE_URL_URI`, favorites, reactions, webhooks and every extension's triples are
 * untouched, and `TripleStore` is not deprecated.
 */
final class PageType
{
    /** An ordinary wiki page: the default, and what an untyped row used to be. */
    public const PAGE = ContentTypeSchema::TYPE_PAGE;
    /** A bazar entry, naming its own form in `body.form_id`. */
    public const ENTRY = ContentTypeSchema::TYPE_ENTRY;
    public const USER = ContentTypeSchema::TYPE_USER;
    public const FILE = ContentTypeSchema::TYPE_FILE;
    /** A form. Content too, and searchable, but not itself a ContentTypeSchema type. */
    public const FORM = 'form';
    /** A value list, the options behind a `liste`/`checkbox` field. */
    public const LIST = 'list';
    /** A comment: a page row whose `parent` names what it comments on. */
    public const COMMENT = 'comment';

    /** The column's default, and what `save()` writes when a caller names no type. */
    public const DEFAULT = self::PAGE;

    /**
     * Legacy `TYPE_URI` triple value => the type it becomes. The empty key is the untyped
     * rows, which were pages.
     *
     * Read by the migration that fills the column, and by nothing else -- once a wiki is
     * migrated no triple carries a type any more.
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

    /** @return list<string> every value the column may hold */
    public static function all(): array
    {
        return [self::PAGE, self::ENTRY, self::USER, self::FILE, self::FORM, self::LIST, self::COMMENT];
    }

    public static function isKnown(?string $type): bool
    {
        return $type !== null && in_array($type, self::all(), true);
    }

    /**
     * The type a legacy triple value named, or null for a triple this concept never
     * described -- a migration marker, a list of favorites.
     */
    public static function fromLegacyTriple(?string $tripleValue): ?string
    {
        return self::BY_LEGACY_TRIPLE[(string)$tripleValue] ?? null;
    }

    /**
     * Whether a form describes rows of this type -- so `FormManager::getByContentType()`
     * has something to answer with. A form and a list are Content, but neither is filled in
     * from a form template, and a comment is a page's body under another name.
     */
    public static function isFormBacked(?string $type): bool
    {
        return ContentTypeSchema::isKnownType($type);
    }
}
