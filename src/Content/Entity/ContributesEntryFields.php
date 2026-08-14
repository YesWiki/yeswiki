<?php

namespace YesWiki\Content\Entity;

/**
 * Supplies extra values about an entry that `Content` does not itself hold.
 *
 * `EntryExtraFieldsService` answers `comments`, `comments_count`, `reactions`,
 * `reactions_count`, `triples` and `linked_data` for a template or an API response. Four of
 * those six are not `Content`'s to know: it was reaching into `CommentService` and
 * `ReactionManager` to answer them, which is how the entry model came to depend on the social
 * features layered over it (ADR-0019).
 *
 * A contributor declares which names it answers and answers them. Discovered by the
 * `yeswiki.entry_fields` DI tag declared against this interface, so a module enrols by
 * implementing it -- the same arrangement as `SuppliesItems` and `ProvidesComponents`.
 *
 * This is the *view* asking each module for its part, which is the honest shape: a page that
 * shows an entry's comments is not the entry depending on comments.
 */
interface ContributesEntryFields
{
    /**
     * The names this contributor answers, e.g. `['comments', 'comments_count']`.
     *
     * Declared rather than discovered from method names so that two contributors claiming the
     * same name is a visible collision rather than a coin toss.
     *
     * @return list<string>
     */
    public function contributedFieldNames(): array;

    /**
     * @param string $name one of `contributedFieldNames()`
     *
     * @return mixed whatever that field is; null if this contributor has nothing for it
     */
    public function contributedField(string $name, string $entryId): mixed;
}
