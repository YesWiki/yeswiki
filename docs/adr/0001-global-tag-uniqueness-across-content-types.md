# Global tag uniqueness across Content types

With `nature` (forms) and `users` folding into `pages` as Content, a wiki page, a form, and a user account can now collide on the same `tag`. We chose **global uniqueness with no type prefix**: a `tag` is unique across every Content type, and creation helpers suggest an alternative (e.g. `JohnDoe2`) on collision, rather than a MediaWiki-style namespace prefix (`User/JohnDoe`) or per-type-scoped uniqueness (`(tag, type)` unique together).

## Considered Options

- **Type-scoped uniqueness** (`(tag, type)` unique) — rejected: makes any bare-tag reference (wikilinks, lookups) ambiguous without also knowing the type.
- **Namespaced prefix baked into the tag** (matching the existing `ThisWikiGroup:` convention used for ACL groups) — rejected in favor of a flat namespace with collision-avoidance tooling instead of a structural prefix.

## Consequences

Existing installs upgrading via the (separately planned, later) migration script may hit real collisions between a page tag and a username or form label that coexist today only because they lived in separate tables — that script will need its own collision-resolution pass.
