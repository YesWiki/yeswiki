# Form-level behavior lives in form properties, not pseudo-fields; plain-English keys; generated tags are slugs

Five bazar "fields" were never entry inputs — they were form-level configuration
smuggled into the template: `titre` (computed entry title), `acls` (entry
permissions + comments toggle), `metadatas` (entry presentation),
`utilisateur_wikini` (account creation from entries), `bookmarklet`. We decided
(2026-07-27, grilling session) to **promote them to form properties** — named
keys in the form body (`entry_title_template`, `entry_read_access`,
`entry_write_access`, `entry_comment_access`, `entry_permit_activate_comments`,
`entry_metadatas`, `entry_creates_user`, `entry_bookmarklet`) applied by the
entry save/render pipeline — and to **delete the five field classes**. The form
template is thereafter the entry-input schema and nothing else.

In the same wave, the legacy French keys are renamed to plain English —
form body: `bn_id_nature`→`id` (numeric id **kept** as the stable identity
distinct from the renameable tag), `bn_label_nature`→`label`,
`bn_description`→`description`, `bn_sem_*`→`sem_*`,
`bn_only_one_entry*`→`only_one_entry*`, `bn_activitypub_*`→`activitypub_*`,
`bn_ce_i18n`→`lang`, `bn_condition`→`condition`, `bn_template`→`template`;
entry bodies: `id_fiche`→`tag`, `id_typeannonce`→`form_id`,
`date_creation_fiche`→`created_at`, `date_maj_fiche`→`updated_at`,
`statut_fiche`→`status`, plus the new always-computed `title` (from
`entry_title_template`, never empty; `bf_titre` loses all special meaning).
Submission artifacts (`antispam`, `valider`, …) are stripped at save.

**Generated tags become slugs**: entry tags and new form tags are produced by
Symfony's `AsciiSlugger`, lowercased, `-2`/`-3` collision suffixes —
`generateWikiName`'s CamelCase generation is retired for generated tags.
User-chosen identifiers (usernames, hand-created page tags) are kept exactly as
typed, and **no existing tag is ever rewritten**.

## Considered Options

- **Keep the pseudo-fields in storage, move only the UI** — rejected: the
  honest model matters more than migration cost in a breaking major, and the
  entry pipeline consulting form properties is simpler than fields with
  `formatValuesBeforeSave()` side effects.
- **Mirror `bf_titre` alongside `title` for a transition** — rejected (clean
  cut): forms with a real `bf_titre` input field keep it as ordinary data
  anyway; only computed-title forms break, disclosed as a one-word template
  edit.
- **Slugify usernames too** — rejected: a username is the identity the person
  typed; it is never transformed.
- **Drop the numeric form id in favor of the tag** — rejected: the tag is
  renameable; entries, default-image filenames and ActivityPub actors key off
  the stable numeric id (ticket-05 design).

## Consequences

Old form/entry revisions keep legacy keys and degrade gracefully (unknown
template types are skipped when preparing fields). Import-from-older-wikis is
removed — the migration is the only conversion path. The API loses the internal
positional `template` arrays and serves the stored JSON as a native array;
`prepared` no longer contains the five retired types. Custom bazar templates
referencing `id_fiche`/`bf_titre`/… on computed values need one-word edits —
a disclosed breaking change of this major. Entry-key renaming applies to latest
revisions only.
