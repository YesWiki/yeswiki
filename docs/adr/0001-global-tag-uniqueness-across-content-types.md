# Global tag uniqueness across Content types

With `nature` (forms) and `users` folding into `pages` as Content, a wiki page, a form, and a user account can now collide on the same `tag`. We chose **global uniqueness with no type prefix**: a `tag` is unique across every Content type, and creation helpers suggest an alternative (e.g. `JohnDoe2`) on collision, rather than a MediaWiki-style namespace prefix (`User/JohnDoe`) or per-type-scoped uniqueness (`(tag, type)` unique together).

## Considered Options

- **Type-scoped uniqueness** (`(tag, type)` unique) — rejected: makes any bare-tag reference (wikilinks, lookups) ambiguous without also knowing the type.
- **Namespaced prefix baked into the tag** (matching the existing `ThisWikiGroup:` convention used for ACL groups) — rejected in favor of a flat namespace with collision-avoidance tooling instead of a structural prefix.

## Amendment (ticket 20, 2026-08-01): the namespace includes routes

The rule above governs Content against Content. It said nothing about Content against a **route**, and the gap was real: dispatch reserved `api` and `doc` in one hardcoded list while URL parsing reserved everything matching `^api` in another, and nothing stopped a page, form, entry or account from being created on either name in the first place. Ticket 18's `/search` is the first case where a name a wiki plausibly already uses becomes a route.

The namespace is therefore **unique across Content types _and_ routes**. `YesWiki\Kernel\Routing\ReservedTags` is the single declaration both dispatch sites now read.

- **The route wins, always.** Content may not shadow a route — otherwise anyone able to create a page could switch off the wiki's API by naming a page after it. This was already the de-facto behaviour; it is now the stated one.
- **Reserved is not the same as taken**, and the two are worded differently wherever a human sees them. "Somebody already has this, here is a free one" sends a webmaster looking for a page; "nobody can ever have this" tells them to pick another name.
- **Scope is the first URL segment only.** A URL is `?Tag/handler`, so handler names (`edit`, `raw`, `xml`, `revisions`, `iframe`) live in the second segment and cannot collide: a page tagged `edit` is `?edit`, its editor `?edit/edit`. They are deliberately not reserved.
- **Matching is case-insensitive.** MySQL's default collation is case-insensitive, so a page tagged `Api` already answers to a lookup for `api`; reserving one spelling would close the hole on SQLite and leave it open on the commonest install.
- **A tag merely starting with a reserved name is ordinary.** `apiculture` is a legal tag — the old prefix match made it unreachable.

### How it is enforced

Where a tag is **generated** (forms, entries, files, and any Content whose tag is derived from something else), `PageManager::suggestFreeTag()` treats reserved exactly like taken and suffixes away from it, so no caller needs to know the list. Where a human **types** one it is refused, and `PageManager::save()` throws as a backstop so no caller can write Content that nothing could ever reach.

Registration is the deliberate exception: it **refuses** rather than suggests, because an account's name _is_ its tag (`UserManager::buildBody()` stores no second copy that could drift). Suffixing would hand somebody the account `api-2` after they typed `api` — a silent rewrite of the one field they chose.

### Content already on a reserved tag

Renamed, by migration, off the reserved name and onto a `suggestFreeTag()` alternative — not left in place. Such a row is unreachable by its own tag today, so leaving it preserves nothing but the invisibility; renaming is what gives it a URL back. It does break inbound links to a page that had them, but a shadowed page never answered those links anyway. Each rename is written to the administrative log, because a URL that changes on its own with no record is the failure mode this decision is trying to avoid.

## Consequences

Existing installs upgrading via the (separately planned, later) migration script may hit real collisions between a page tag and a username or form label that coexist today only because they lived in separate tables — that script will need its own collision-resolution pass.

Adding a route to core now takes a name away from every wiki that upgrades. `ReservedTagsTest` asserts `ReservedTags::NAMES` still matches the real route table, so a new first-segment route fails the suite until the name is declared — which is the moment to ask whether the route should be nested under an existing prefix instead of claiming a new word.
