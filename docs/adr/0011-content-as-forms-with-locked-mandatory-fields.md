# Page, User and File are forms; their mandatory fields are locked by code, not by a stored flag

Wave one made forms, users and files rows in `pages`; ticket 09 gave every Content type one body shape. Ticket 10 finishes the unification by giving them one *schema* mechanism: Page, User and File are **forms**, with an ordinary form template edited in the ordinary designer, so there is one model, one designer and one storage shape for every kind of Content in the wiki.

Each of the three has a mandatory core structure a webmaster cannot break — Page: `title`, `content`, `keywords`; User: `username`, `password`, `email`; File: `original_filename`, `stored_filename`, `uploaded_from` (what the file service needs to serve a download, plus the owning-page association that seeds its read ACL). Those fields are **locked**: they cannot be deleted or retyped. Everything else about them is the webmaster's — label, help text, order, Field ACL — and webmaster-added fields sit in the same list beside them with no special casing.

## Locked-ness is declared in code, not stored on the field

The obvious implementation is a `locked: true` attribute on the stored field object. We rejected it: every write vector this ticket has to guard — the designer, the API, CSV import, form duplication, a hand-edited template — would then also be a way to clear the flag. Protection that travels inside the thing being protected is not protection.

So `ContentTypeSchema` declares the structure in PHP, and a form body carries only `content_type` naming which structure applies. `ContentTypeSchema::enforce()` runs inside `FormManager::templateToStorage()`, the single canonicalization point every template write already passes through, and **repairs rather than rejects**: a template that arrives without a locked field gets it back, in declared order at the front, while the webmaster's edits to the rest of the template stand. A locked field that arrives retyped keeps its declared type and loses nothing else.

`content_type` is immutable once set. Retyping a User form into an ordinary entry form would otherwise unlock its core fields — the same hole by another route.

## The template definition widens, and does not re-admit pseudo-fields

ADR-0010 narrowed the form template to "the entry-input schema and nothing else", having just evicted five pseudo-fields that were really form-level configuration. That narrowing stands. What widens is only *whose* inputs a template may describe: a page's, a user's and a file's, as well as an entry's. Every locked field is a real input with a real value in the body — `content` holds the page's markup, `password` the hash, `stored_filename` the name on disk. None of them is behaviour smuggled into the schema, which is what ADR-0010 forbade.

## ADR-0003 is unchanged and explicitly not amended

The password hash stays in the versioned `body` as a locked `mot_de_passe` field under Field ACL. This was re-examined and the conclusion is stronger than before: Field ACL is a property of a *template field*, so moving the hash to `metadata` would remove the very mechanism protecting it and leave protection-by-location — precisely what ADR-0003 rejected. Enforcement applies uniformly to historical revisions, not only the latest, and now covers more render paths than when ADR-0003 was written.

## Considered Options

- **Keep mandatory fields implicit and outside the template** — rejected: leaves each type's edit form half-designed and half-hardcoded, which is the split this ticket exists to close.
- **A base template plus a webmaster extension template** — rejected: adds a second template concept and a set of merge semantics to define, for no gain over one list with some locked rows.
- **A stored `locked` attribute on the field object** — rejected, see above.

## Consequences

Adding a field to a type's core structure is a code change, and existing forms of that type acquire it the next time their template is written (or by the migration that seeds it). Removing one is likewise a code change, and leaves the field behind in stored templates as an ordinary, now-deletable field — which is the right default: data a webmaster's site depends on should not vanish because core stopped requiring it.
