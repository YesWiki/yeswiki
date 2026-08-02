<?php

namespace YesWiki\Content\Field;

/**
 * The field types that put nothing into the search index (ticket 18 / ADR-0015).
 *
 * Three separate reasons, which is why this is a shared trait and not a single rule:
 *
 * - **Disclosure.** An address in a wiki-wide text index makes search an address-harvesting
 *   endpoint, and a password hash has no business being matchable at all. `EmailField` is
 *   the one place that can close the first of those once: the seeded `Annuaire` form ships
 *   `bf_mail` with `"read_access":"*"` under the label *"Email (n'apparaîtra pas sur le
 *   web)"*, so the promise the label makes has never been kept by the ACL.
 * - **Envelope.** A stored filename is a UUID, a date is a number, a URL is mostly `https`.
 *   Indexing them means "search 2026, match everything edited this year" -- the same defect
 *   that motivated this ticket, one layer down.
 * - **Structure.** Coordinates and opening hours are data a filter queries, not prose a
 *   reader searches for.
 *
 * A field type that wants back in only has to stop using this trait. What it must not do is
 * become a name in a list somewhere else -- that is the anti-pattern ADR-0012 retired.
 */
trait ContributesNoSearchableText
{
    public function searchableText($entry): string
    {
        return '';
    }
}
