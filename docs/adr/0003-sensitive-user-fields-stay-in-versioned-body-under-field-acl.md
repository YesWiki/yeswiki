# Sensitive user fields stay in the versioned body, protected by Field ACL

Once `users` become `pages` rows (type-tagged via `TripleStore::TYPE_URI`, matching the existing convention for bazar entries and PR #1333's treatment of forms), every profile edit — including a password change — would, by the same "new row per edit" mechanism as ordinary wiki pages, leave the old password hash sitting in that tag's page history.

We decided **not** to carve sensitive fields (password hash, tokens) into a separate, non-versioned table. Instead they stay hashed in the same versioned `body`/`metadata` as everything else, and are hidden from display via **Field ACL** — the existing per-field read/write ACL mechanism already implemented for bazar entry fields (`FIELD_READ_ACCESS`/`FIELD_WRITE_ACCESS` in `tools/bazar/fields/BazarField.php`, enforced by `canRead()`/`canEdit()`). Field ACL enforcement must apply uniformly to historical revisions, not just the latest — otherwise the page-history view becomes a way to read old password hashes despite the current view hiding them.

## Considered Options

- **A small dedicated `user_secrets`-style table**, updated in place, holding only credentials — rejected as effectively reintroducing a narrower version of the `users` table we're trying to eliminate, and as a second storage mechanism to keep in sync.
- **Non-versioned columns bolted onto the otherwise-versioned `pages` row** — rejected: invites bugs where a code path forgets which columns are exempt from the revision-copy-forward logic.

## Consequences

Every render path that displays a `users`-type Content field — current view, history view, diffs, exports — must go through the same Field ACL check. A new render path that skips it is a credential leak, not just a display bug.
