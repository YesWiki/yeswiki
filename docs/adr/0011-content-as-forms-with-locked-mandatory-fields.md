# Page, User and File are forms; their mandatory fields are locked by code, not by a stored flag

Wave one made forms, users and files rows in `pages`; ticket 09 gave every Content type one body shape. Ticket 10 finishes the unification by giving them one *schema* mechanism: Page, User and File are **forms**, with an ordinary form template edited in the ordinary designer, so there is one model, one designer and one storage shape for every kind of Content in the wiki.

Each of the three has a mandatory core structure a webmaster cannot break — Page: `title`, `content`, `keywords`; User: `username`, `password`, `email`, `profile_picture`; File: `file_content`, the bytes themselves ([amended](#a-derived-attribute-is-not-an-input), see below). Those fields are **locked**: they cannot be deleted or retyped. Everything else about them is the webmaster's — label, help text, order, Field ACL — and webmaster-added fields sit in the same list beside them with no special casing.

## Locked-ness is declared in code, not stored on the field

The obvious implementation is a `locked: true` attribute on the stored field object. We rejected it: every write vector this ticket has to guard — the designer, the API, CSV import, form duplication, a hand-edited template — would then also be a way to clear the flag. Protection that travels inside the thing being protected is not protection.

So `ContentTypeSchema` declares the structure in PHP, and a form body carries only `content_type` naming which structure applies. `ContentTypeSchema::enforce()` runs inside `FormManager::templateToStorage()`, the single canonicalization point every template write already passes through, and **repairs rather than rejects**: a template that arrives without a locked field gets it back, in declared order at the front, while the webmaster's edits to the rest of the template stand. A locked field that arrives retyped keeps its declared type and loses nothing else.

`content_type` is immutable once set. Retyping a User form into an ordinary entry form would otherwise unlock its core fields — the same hole by another route.

## The template definition widens, and does not re-admit pseudo-fields

ADR-0010 narrowed the form template to "the entry-input schema and nothing else", having just evicted five pseudo-fields that were really form-level configuration. That narrowing stands. What widens is only *whose* inputs a template may describe: a page's, a user's and a file's, as well as an entry's. Every locked field is a real input with a real value in the body — `content` holds the page's markup, `password` the hash, `file_content` the uploaded bytes. None of them is behaviour smuggled into the schema, which is what ADR-0010 forbade.

## A Content type answers for more than its locked fields

Declaring the structure in code turned out to answer three further questions that only have one right answer per type, and that were each getting a wrong one by default.

**Which form describes a row.** A bazar entry says so itself, in `body.form_id`. A page, an account and a file do not: which form describes them is decided by their `TYPE_URI` triple, and for a page by the *absence* of one — carrying no type triple is exactly what makes a row a page. Searching therefore cannot ask for `body.form_id IN (...)` joined to the `fiche_bazar` type, as it always had; it asks the form's Content type what its rows look like (`SearchManager::rowsBelongingTo()`). Until it did, a bazar view of the Pages form came back empty with nothing to explain it.

**How a Content of that type is named.** `entry_title_template` (ADR-0010) defaulted to the bazar convention `{{bf_titre}}`, a field no built-in type has, so every page listed under a blank title. A built-in type names itself with one of its own fields — `{{title}}`, `{{username}}`, `{{original_filename}}` — and only an ordinary form falls back to the historical convention. (Two of those three are locked fields; a file's `original_filename` became a plain body key when the amendment below evicted the derived attributes, and the title template resolves against the body either way.)

**Which form properties apply at all.** "Submitting an entry creates a user account" and "install a bookmarklet that files a web page as an entry" describe visitor submissions to a webmaster's form. A page, an account and an uploaded file are not submitted that way — the User form *is* the accounts — so a built-in type drops those properties, on read as well as on write, and the designer does not offer them. A form that presents an option it will not honour is worse than one that hides it.

The last two are stripped and defaulted on **read** as well as on write, for the same reason locked fields are enforced on read: a body can arrive carrying the wrong thing by a route that never came through `FormManager`, and the next ordinary write then persists it correct.

A Content type also answers **which of its locked fields restates the row's tag**: an account's `username` is its tag, the way a bazar entry's `tag` is. Read paths fill it in from the tag rather than storing the same string twice, and the editor does not offer it as an input — a field that restates an identity should not be able to drift from it.

## The editor edits the form of the row's own Content type

The consequence at the other end. Since a page's title and keywords are *fields*, the editor renders them from the form's template rather than hardcoding their markup, and a webmaster who adds a field to that form gets an input for it. Fields declared before `content` render above the ACeditor and those after it below, so the designer lays a page out the same way it lays an entry out. `content` is the one field the editor renders itself.

But *which* form is the row's own, not always Page. An account and an uploaded file have no `content` field, so they get no markup editor and no `content` is written — that is what keeps a page-shaped body off Content that is not a page. `ContentTypeResolver` is the one place that knows how to go from a row to its form: by `body.form_id` for a bazar entry, by the type triple otherwise, and by the *absence* of a triple for a page.

"Nothing changed" consequently means the whole body and not just the markup — retitling a page without touching its prose is a real edit, and used to be silently discarded.

## Every Content has a name

`entry_title_template` can leave a Content nameless: a page whose title was never filled in, a template naming a field that was since deleted. Two rules make that impossible to show a visitor. A `{{field}}` with no value is substituted by **nothing** rather than left standing — an unresolved placeholder is not a name. And a title that comes out empty falls back to the row's **tag**, the only other name a Content is guaranteed to have and the one already in its URL. A blank column in every list was the alternative.

## A derived attribute is not an input

*Amended by ticket 13.* The File type was originally given three locked **text fields** —
`original_filename`, `stored_filename`, `uploaded_from` — on the reasoning below that every
locked field is a real input with a real value in the body. For a file that reasoning was
wrong in both directions. Nobody types those values: all of them are computed from an
upload, and offering `stored_filename` as a text box is offering to break the download.
And an input that is never submitted does not merely go unused — it writes an empty string
over what was there, so saving a File from its own edit form blanked both filenames and
404'd the file being edited.

So the File type declares one locked field, `file_content`, the bytes; and
`original_filename`, `stored_filename`, `size`, `mime_type` and `uploaded_from` stay body
keys that `FileManager` writes. A form template describes what someone fills in. What the
system derives from what they filled in belongs in the body, not in the schema — which is
the same line ADR-0010 drew when it evicted the pseudo-fields.

The rest of the section below stands: `content` really is the page's markup and `password`
really is the hash, both typed in, both locked.

## ADR-0003 is unchanged and explicitly not amended

The password hash stays in the versioned `body` as a locked field of type `mot_de_passe` (named `password`) under Field ACL. This was re-examined and the conclusion is stronger than before: Field ACL is a property of a *template field*, so moving the hash to `metadata` would remove the very mechanism protecting it and leave protection-by-location — precisely what ADR-0003 rejected. Enforcement applies uniformly to historical revisions, not only the latest, and now covers more render paths than when ADR-0003 was written.

## Considered Options

- **Keep mandatory fields implicit and outside the template** — rejected: leaves each type's edit form half-designed and half-hardcoded, which is the split this ticket exists to close.
- **A base template plus a webmaster extension template** — rejected: adds a second template concept and a set of merge semantics to define, for no gain over one list with some locked rows.
- **A stored `locked` attribute on the field object** — rejected, see above.

## Consequences

Adding a field to a type's core structure is a code change, and existing forms of that type acquire it the next time their template is written (or by the migration that seeds it). Removing one is likewise a code change, and leaves the field behind in stored templates as an ordinary, now-deletable field — which is the right default: data a webmaster's site depends on should not vanish because core stopped requiring it.
