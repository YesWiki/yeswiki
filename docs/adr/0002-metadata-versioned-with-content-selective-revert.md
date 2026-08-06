# Metadata (including ACLs) is versioned with Content; revert is selective by default

The `acls` table is being replaced by a `metadata` JSON column on `pages`, holding ACLs plus other user-editable facts (social metadata, licensing, source URLs, themes) — absorbing and retiring the older, non-versioned `AdminContentController::METADATA_PROPERTY` triple. We decided this column rides along in the same revisioned row as content (a new `pages` row per edit), rather than being mutated in place outside the revision history, because users want permission and metadata changes to be revertable the same way content is.

That coupling creates a footgun: reverting to an old content revision would silently restore that revision's ACLs too, which could reopen access an admin deliberately closed after sensitive content was added. We resolved this by making **revert selective by default** — restoring content alone, leaving current Metadata/ACLs untouched — with full revert (content + metadata together) as a separate, explicit action.

## Considered Options

- **Metadata mutated in place, outside the revision chain** (like the `acls` table works today) — rejected: users specifically want ACL/metadata history and revertability, which a non-versioned column can't offer.
- **Revert always restores metadata along with content, no selective option** — rejected: too easy to accidentally reopen access via a routine content-wording revert.

## Amendment (ticket 09): the body/metadata boundary is now a rule, not a habit

When this decision was taken, `metadata` was described loosely as "user-editable facts that aren't the body itself". In practice it accumulated whatever had nowhere better to go, because `body` could not hold structured data for a wiki page — it was raw markup. Ticket 09 removes that constraint by making `body` a JSON object for **every** Content type, so the boundary can now be stated:

- **`metadata` holds how Content is presented and who may see it**: `acls`, `theme`, `squelette`, `style`, `favorite_preset`, `bgimg`, `lang`. Nothing else.
- **`body` holds what the Content _is_**: its own data, including every webmaster-added field. A field a webmaster can add, rename or delete is a body field by definition.

Two groups of keys moved out of `metadata` under that rule:

- **File attributes** (`original_filename`, `stored_filename`, `size`, `mime_type`, `uploaded_from`) — a file's own data, and the fields ticket 10 turns into the File form's mandatory structure.
- **ActivityPub federation settings** (`enabled`, `username`, `private_key`, `public_key`) — carried only by `form`-type Content, where CONTEXT.md already lists `activitypub_*` among Form properties, which live in the form's body.

**Page keywords also leave `triples` for `body.keywords`.** They were the last "fact about a page" stored outside the page's own row, which forced every keyword reader to join `triples` and made a page's data non-atomic with the page. Triples keep the reverse index (tag → pages) as a **derived** structure, rebuilt from bodies and never authoritative; `TripleStore::TYPE_URI` remains the Content-type discriminator and is untouched by this.

The selective-revert rule above is unchanged and becomes more important, not less: with webmaster data fields now in `body`, a content-only revert restores more of what a user thinks of as "the page" while still leaving permissions alone.
