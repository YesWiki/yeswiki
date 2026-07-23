# Metadata (including ACLs) is versioned with Content; revert is selective by default

The `acls` table is being replaced by a `metadata` JSON column on `pages`, holding ACLs plus other user-editable facts (social metadata, licensing, source URLs, themes) — absorbing and retiring the older, non-versioned `AdminContentController::METADATA_PROPERTY` triple. We decided this column rides along in the same revisioned row as content (a new `pages` row per edit), rather than being mutated in place outside the revision history, because users want permission and metadata changes to be revertable the same way content is.

That coupling creates a footgun: reverting to an old content revision would silently restore that revision's ACLs too, which could reopen access an admin deliberately closed after sensitive content was added. We resolved this by making **revert selective by default** — restoring content alone, leaving current Metadata/ACLs untouched — with full revert (content + metadata together) as a separate, explicit action.

## Considered Options

- **Metadata mutated in place, outside the revision chain** (like the `acls` table works today) — rejected: users specifically want ACL/metadata history and revertability, which a non-versioned column can't offer.
- **Revert always restores metadata along with content, no selective option** — rejected: too easy to accidentally reopen access via a routine content-wording revert.
