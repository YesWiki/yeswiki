# YesWiki (ectoplasme rewrite)

YesWiki is a wiki engine. The `ectoplasme` branch is its next MAJOR version: a standardization rewrite that folds most bundled `tools/` into core services/actions/handlers and unifies the data model around a single `pages` table.

## Language

**Content**:
Anything stored as a row in the `pages` table: an ordinary wiki page, a form definition (formerly the `nature` table), or a user account (formerly the `users` table). Distinguished by a triple `(resource=tag, property=TripleStore::TYPE_URI, value=<type>)` in the `triples` table — the existing mechanism already used to mark bazar entries (`value='fiche_bazar'`, see `EntryManager::TRIPLES_ENTRY_ID`) and extended by [PR #1333](https://github.com/YesWiki/yeswiki/pull/1333) to mark forms; there is no `type` column on `pages` itself.
_Avoid_: Page (when the type could be a form or user), record, entity, "type column" (it's a triple, not a column).

**Content type**:
Which kind of Content a row is: `entry`, `page`, `user`, `file` (plus `liste`). Declared per row by the `TripleStore::TYPE_URI` triple, and named on a form's body by `content_type` (`ContentTypeSchema`) to say which core structure that form describes. Page, User and File are forms like any other — same designer, same template, same storage (ADR-0011).
_Avoid_: "nature" (the retired table), "kind", treating `entry` as the only type with a template.

**Locked field**:
A field in a Content type's mandatory core structure — Page's `title`/`content`/`keywords`, User's `username`/`password`/`email`, File's `original_filename`/`stored_filename`/`uploaded_from`. It cannot be deleted or retyped; its label, help text, order and Field ACL are ordinary and editable, and webmaster-added fields sit beside it in the same list. Locked-ness is declared in `ContentTypeSchema`, never stored as an attribute on the field — a stored flag would be clearable through the very write vectors it has to survive (ADR-0011).
_Avoid_: "system field", "read-only field" (only deletion and retyping are barred), "required field" (that is the separate `required` attribute a visitor's input must satisfy).

**Tag**:
The globally unique identifier of a piece of Content, regardless of its type. A wiki page, a form, and a user account all draw from the same tag namespace — creating a user named `JohnDoe` is only possible if no page or form already holds that tag. Helper functions resolve collisions by suggesting an alternative tag rather than silently namespacing by type. *Generated* tags (bazar entries, new forms) are lowercase slugs (`l-ete-a-nantes`, collisions suffixed `-2`); *user-chosen* tags (usernames, hand-created pages) are kept exactly as typed — usernames are never slugified. Existing tags are never rewritten.
_Avoid_: Name, key, CamelCase id generation (`genere_nom_wiki` is retired for generated tags).

**Body**:
The `body` column on each `pages` row: **a JSON object for every Content type** (ticket 09). It holds what the Content *is* — its own data, including every webmaster-added field. A wiki page and a comment carry their wiki markup as a string under `content`; a bazar entry carries its form fields at the top level; a form carries its `template` and Form properties. The container is uniform, the keys are per-type. Encoded and decoded through `PageBody` (`src/Content/Entity/PageBody.php`), never with a bare `json_encode`. The column stays `TEXT`/`LONGTEXT` holding JSON *text* — MySQL cannot build the `pages` FULLTEXT index on a native JSON column.
_Avoid_: "the markup" (only pages and comments have any), sniffing the shape from `body[0] === '{'` (83 pages in the reference wiki open with an action call — the type comes from the `TYPE_URI` triple).

**Metadata**:
A JSON column on each `pages` row holding **how Content is presented and who may see it**, and nothing else: `acls` (replacing the `acls` table), `theme`, `squelette`, `style`, `favorite_preset`, `bgimg`, `lang`. Versioned along with the row — reverting Content to a prior revision reverts its Metadata too. Absorbs and replaces the pre-existing, non-versioned `AdminContentController::METADATA_PROPERTY` triple (`http://outils-reseaux.org/_vocabulary/metadata`) — that older mechanism is retired, not kept alongside the new column. Anything a webmaster can add, rename or delete is a Body field, not Metadata; ticket 09 moved file attributes and ActivityPub settings out of Metadata on that rule (see ADR-0002's amendment).
_Avoid_: ACLs (too narrow — ACLs are one key within Metadata, not the whole column), properties, attributes, the old `METADATA_PROPERTY` triple (retired), using it as a home for data fields.

**`yw-*` core styles**:
The minimalist CSS/JS design system shipped by core after Bootstrap is dropped, namespaced under a `yw-*` class prefix so it can't collide with whatever a theme brings in. Replaces per-theme Bootstrap dependence; existing themes (including `yeswiki-theme-bootstrap3`) are expected to be retired rather than ported, and new themes are CSS-framework-agnostic — free to use any framework or none, since core no longer imposes one. Its interactive behavior is built on **vanilla JS and htmx only** — no jQuery, no Bootstrap JS, no other JS framework, starting with this major release.
_Avoid_: Bootstrap classes (retired from core; a small legacy vocabulary present in stored wiki content stays styled by core — see ADR-0004's ticket-16 amendment — but is not an API for new code), jQuery (retired from core; one disclosed island remains, see ADR-0005's ticket-16 amendment and ticket 26), theme framework (themes have none imposed).

**Field ACL**:
A per-field (not per-page) read/write access-control list, using the same ACL syntax as page-level Metadata ACLs. Already implemented for bazar entry fields (`read_access`/`write_access` attributes, `src/fields/BazarField.php`, enforced by `canRead()`/`canEdit()`); this rewrite extends it to `users`-type Content, where it hides sensitive fields (e.g. the hashed password) that otherwise live in the same versioned `body` as everything else, rather than carving those fields into a separate non-versioned table.
_Avoid_: Private field, restricted field (both used informally in existing bazar code/UI, but "Field ACL" is the precise mechanism name).

**Form template**:
The JSON array of field objects stored under `template` in a form's body — the schema of the inputs filled when creating that form's Content, and nothing else. Since ticket 10 that Content may be a page, a user or a file as well as an entry, and some of its fields may be locked (see **Locked field**) — what has *not* widened is what a template is for. Attribute keys are the `FIELD_*` constant names of the handling field class. Form-level behavior (title computation, entry ACLs, presentation, account creation, bookmarklet) never lives here — those are Form properties.
_Avoid_: `***` syntax (legacy, read-only), `bn_template` (renamed), "prepared json" (ambiguous — see Prepared fields), pseudo-field / special field (retired concept).

**Form property**:
A named key in a form's body holding form-level configuration: identity (`id`, `label`, `description`, `lang`), behavior (`entry_title_template`, `entry_read_access`, `entry_write_access`, `entry_comment_access`, `entry_permit_activate_comments`, `entry_creates_user`, `entry_bookmarklet`), presentation (`entry_metadatas`), and the legacy-carried `sem_*`, `only_one_entry*`, `activitypub_*`, `condition`. Plain-English names; the `bn_` prefix is retired.
_Avoid_: `bn_*` keys (renamed), pseudo-fields (form behavior stored as fake template fields — retired).

**Field role**:
Core's question about a form's fields — "which one holds the start date?" — answered by the form rather than by a hardcoded field name. Roles: `start_date`, `end_date`, `image`, `email`, `description`, `geolocation`. Resolved through `FieldRoleResolver`, from the form's explicit `field_roles` property when it has one and otherwise from the field's own type (a `listedatedeb` field is the start date), so existing forms need no migration. Generalises ticket 27's `entry_title_template` (ADR-0012).
_Avoid_: reading `bf_date_debut_evenement`/`bf_latitude`/`bf_titre` or any literal field name from core — field names are user data; "magic field name" is the retired anti-pattern.

**Prepared fields**:
The runtime field objects constructed from the Form template (one per real field), serialized in the API as `prepared` — what rendering consumers iterate. The internal positional arrays feeding the field constructors are an implementation detail and are not exposed anywhere.
_Avoid_: "prepared json" for the *stored* template (the stored template is the Form template; `prepared` is derived from it), positional template arrays in any public payload.

**Entry title**:
The `title` key of an entry, always computed at save from the form's `entry_title_template` (a `{{field}}` substitution template; picking a single field stores `{{bf_x}}`). Also the source for the entry's generated tag (slug).
_Avoid_: `bf_titre` as the title mechanism (it may exist as an ordinary input field a template references, but nothing reads it as "the title" anymore).

**Entry system keys**:
The non-field keys every entry body carries: `tag` (its page tag), `form_id`, `title`, `created_at`, `updated_at`, `status`. Submission artifacts (`antispam`, `valider`, …) are stripped at save, never stored.
_Avoid_: `id_fiche`, `id_typeannonce`, `date_creation_fiche`, `date_maj_fiche`, `statut_fiche` (all renamed).

## Decisions so far

- No blanket rule on in-place migration of existing installs' data during this rewrite — decided pragmatically per ticket, not deferred wholesale. `acls` was dropped without migrating (reconfigurable via the UI after a reset). `nature` → `pages` (ticket 05) IS migrated in place, since forms are load-bearing content bazar entries depend on to render at all — losing them silently breaks every existing entry, not just access control. Each future ticket should make its own call on whether dropping without migrating is an acceptable reset (like `acls`) or would silently break things (like `nature`), and write a migration when it would.
- `tag` uniqueness is global across all Content types (not scoped per type, not namespaced by prefix). Collisions are avoided at creation time via a suggested-alternative-tag helper.
- Metadata (including ACLs) is versioned along with Content: it rides in the same revisioned `pages` row rather than being mutated in place, so permission history is reconstructable and revertable like content.
- Reverting Content to a prior revision is selective by default (content only); a full revert that also restores that revision's Metadata/ACLs is a separate, explicit action.
- Sensitive `users`-type fields (password hash, tokens) are NOT carved into a separate table — they stay hashed in the same versioned `body` as everything else, protected by Field ACL. Field ACL enforcement applies uniformly to historical revisions, not just the latest.
- Forms keep a stable identifier distinct from their (renameable) `tag`, matching [PR #1333](https://github.com/YesWiki/yeswiki/pull/1333)'s approach. Renaming a form cascades integrity updates to its entries and referencing page content; a former-tag alias may be kept as a triple so previously published sensitive URLs (e.g. ActivityPub actor URLs) keep resolving.
- The pre-existing triples-based `METADATA_PROPERTY` mechanism (non-versioned page facts, e.g. `theme`) is retired in favor of the new versioned `pages.metadata` JSON column — no dual mechanism.
- Extensions live in two places: a shared root `extensions/` (source-scoped, available to every farm instance, instances cannot write into it) and instance-local `custom/extensions/` (instance-scoped, per-instance-only plugins). Reuses the existing lookup precedence already implemented for `custom/tools/{ext}` vs `{sourceDir}/tools/{ext}` in `src/autoload.inc.php` — instance-local shadows shared-root, no new mechanism.
- Which tools get absorbed into core has no fixed criterion — it's periodically re-evaluated with the user community. Confirmed for core (beyond aceditor, attach, bazar, contact, autoupdate, login, rss, security, syndication, tags, templates, toc): qrcode, webhooks, herse, accountactivationbyemail. `lang` and `progressbar` are removed outright, not moved to extensions.
- Bootstrap is dropped from core in favor of a minimal `yw-*`-prefixed CSS/JS design system. Existing themes are expected to be retired, not ported (including `yeswiki-theme-bootstrap3`). New themes are CSS-framework-agnostic.
- From this major release on, core JS is vanilla JS and htmx only — no jQuery, no other JS framework. Piloted first on the `tags` rewrite, then applied everywhere.
- `attach` upload gets one consolidated API-route-backed path, retiring both the legacy page-handler and the vendored `qqFileUploader`. Download enforces the owning page's read ACL by default (a behavior change from today's unauthenticated-by-URL download); a per-file/per-field opt-out flag keeps public embedding possible.
- `tags` gets a real `GET /api/tags` endpoint with pagination and search criteria, replacing "ship every tag in the wiki to every editor" with live search-as-you-type.
- `contact` rewrite is scoped to moving mail-sending to an API route and consolidating through the `Mailer` service (today it bypasses `Mailer` via a bare `send_mail()` call). Today's complete absence of CSRF/spam protection is explicitly out of scope for this pass — deferred to a dedicated later security pass.
- `aceditor` keeps the Ace editor library as-is; only its jQuery/Bootstrap wrapper and toolbar are replaced (vanilla JS/htmx, per the core-wide JS rule).
- `tools/templates/` (theme/skin selector, bundled today with ~25 unrelated legacy layout actions including a rights-management action) is split during core absorption rather than moved as one unit: the actual theme/skin-selection logic becomes a core service; the grab-bag actions are triaged individually (delete dead code, fold misplaced ones like `GererDroitsAction` into the relevant absorption e.g. `login`/`security`).
- `security` (today one `SecurityController` bundling hashcash edit-lock, comment spam/captcha, and wiki hibernation status — three unrelated concerns) is likewise split into separate core services during absorption, so the later dedicated security pass has clean seams to extend rather than untangling a bundle first.
- `toc`'s client-side sticky/scroll-spy variant (`tocjs.php`, jQuery + Bootstrap's `.affix()`/`.scrollspy()` JS components, branches on Bootstrap 2 vs 3) is dropped entirely. Only the server-side, CommonMark-based static TOC (`toc.php`) becomes core.
- `login`: all `users`-table access is routed exclusively through `UserManager` as part of this absorption — `tools/login/actions/listusers.php`'s raw SQL bypass is fixed now (found to be the only other direct-SQL access point besides `UserManager` itself), so `UserManager` is the single seam the later users→pages migration needs to touch.
- `accountactivationbyemail` (becoming core): activation status/key move from standalone triples into the `users`-type page's body, protected by Field ACL — same treatment as password hash (ADR-0003). The token also gets an expiry, reusing the `key:issuedAt` + TTL + periodic-purge convention from `UserManager::purgeExpiredPasswordRecoveryKeys()` — **note: that method exists only on `doryphore-dev` (commit `e33edd3c2`, security advisory GHSA-x3xh-4hx3-rgm7 fix), not yet merged into `ectoplasme`; needs merging before this can be implemented.**
- `autoupdate`: farm-wide core/tools/theme updates target the shared `YESWIKI_SOURCE_DIR` consistently, triggerable only from an admin-designated instance, not any instance (see ADR-0007).
- `webhooks` (becoming core) stays scoped to comment and Bazar-entry events, as today — not expanded to fire on form or user-account changes despite those now being Content too.
- `syndication`, `rss`, `qrcode`, `herse` move into core with no new decisions needed: `syndication`/`rss` raise no tensions beyond the already-decided stable form-ID (`syndication` uses `id_typeannonce`); `qrcode`'s Bootstrap modal falls under the existing Bootstrap/jQuery-removal rule and its Bazar-backed relation-badge storage is already clean; `herse` is a trivial HTTP Basic Auth gate with no DB/JS/routes.
- Before any implementation starts: `doryphore-dev` is fully reconciled into `ectoplasme` (65 unmerged commits, 12+ security-advisory fixes — see ADR-0008), and every `tools/*/index.php` direct-access guard file is deleted outright (not just its debug tracing code stripped) — direct access to `tools/*` is no longer reachable now that requests funnel through the Symfony kernel/routing, so the guard itself is dead legacy protection.
