# Search is a denormalised index table in the wiki's own database

Full-text search read the `body` column directly: `body LIKE '%phrase%'`, in `NewtextsearchAction:206`. Since ticket 09 made every body a JSON object, that column contains its own keys as text — so searching for `title`, `content`, `keywords`, `status`, `form_id` or `created_at` matched **every page in the wiki**. The action had also come to depend on the envelope deliberately, matching bazar list values with `body LIKE '%"propertyName":"key"%'` (line 176). This reads to a user as "search is bad" rather than as a bug, which is why it could sit unreported.

The fix is a derived index: a table holding what a Content _says_, maintained on write, rebuildable from a command. Text search stops being a query against storage and becomes a query against an index, which also decouples it from body storage — leaving a future native-JSON `body` (ticket 19) to be judged purely on field-query merits.

Two things about the index are not obvious from that description, and both are load-bearing: it lives in the **wiki's own database**, and it is a **table keyed by tag**, not a column on `pages`.

## Why not the existing extension

`yeswiki-extension-fulltextsearch` already solves this, well: 1531 lines, cleanly factored behind a single `SealFacade`, with PDF attachment indexing, a page-exclusion list, an htmx UI and a Playwright suite. Its index document — `tag`, `tag_searchable`, `title`, `fulltext` — is _the same model_ this ADR adopts. It was evaluated as the candidate implementation and rejected on one point: **where the index lives.**

It is built on SEAL (`cmsig/seal`), which ships adapters for Elasticsearch, OpenSearch, Meilisearch, Algolia, Loupe, Solr, RediSearch and Typesense — **none relational**. There is no MySQL, PostgreSQL or Doctrine DBAL adapter, and Loupe is not a SQLite-flavoured adapter that could be repointed: Loupe _is_ an engine whose storage format is SQLite, requiring `ext-pdo_sqlite >= 3.35`, FTS5, `toflar/state-set-index` for typo tolerance, `wamania/php-stemmer` and `ext-intl`. So a MySQL wiki would run a second, unrelated storage engine as a file under `private/`, outside its own backups, needing an extension PHP builds don't all have. Typesense, the only other supported driver, is a separate server — unavailable on the shared hosting most YesWikis run on.

Keeping the index in the wiki's database is not tidiness. **It is what makes access control exact.** `SealSearchService` cannot filter in the engine, so it post-filters: fetch a page of results, drop what `hasAccess()` refuses, loop, and give up at `MAX_SCAN_RESULTS = 1000`. Result counts and pagination are therefore approximate on any wiki with restricted content, and a narrowly-permissioned user can silently exhaust the scan. An index that sits beside `pages` can put the ACL predicate _in the query_.

The extension is retired rather than kept as a pluggable backend. A swappable engine seam would have to be built to the weaker of the two contracts — the one that cannot filter — and the whole point is the stronger one. Its PDF text extraction is worth having and becomes a separate ticket.

Nothing would have landed as-is regardless: the extension is written against doryphore (`YesWiki\Bazar\Field`, `YesWiki\Core\Service\AclService`, `Performer`, `id_typeannonce`, a raw-markup `$page['body']`), all of which moved in this rewrite.

## Why a table, not a column

The ticket that proposed this asked for a `search_text` **column**, mirroring the derived-keyword index ticket 09 built. That is wrong here, because `pages` is **revision-keyed**: one row per revision, `latest = 'Y'` marking the current one. A column would store derived text on every historical revision, none of which search ever reads — and since metadata and ACL changes forge a new revision carrying the body forward, ordinary permission edits would mint more copies.

A table keyed by tag also makes the backfill cheap. There is no `ALTER TABLE pages` on a `LONGTEXT` table of unknown size on installs the maintainer does not control; the index is created empty, filled by a command, and dropped and refilled the same way.

(`body_r` was considered as a free column: `TEXT NOT NULL`, written `''` at all four insert sites and read nowhere since WikiNi. It inherits the same per-revision waste, and repurposing a column whose name means "rendered body" to mean "search text" trades a migration for a permanent lie.)

## The row is fully denormalised, and the query joins nothing

`search_index(tag, acl, page_read_acl, content_type, form_id, title, text, updated_at)`. Match, field-level ACL, page-level ACL, content-type filter and sort all resolve in one index scan on one table.

The page's read ACL is a column rather than a live read of `pages.metadata` through `AclService::updateRequestWithACL()`. That is sound because **every ACL write already creates a revision** — `AclService` inserts one, so `page.updated` fires, so the row is already being rewritten. Group membership is not denormalised and does not need to be: the predicate evaluates a user's groups at query time, so adding someone to a group takes effect immediately with no reindexing.

This is a real coupling, and it is the one thing here that could rot silently. A future write path that changes an ACL without revisioning would leave the index authoritative and wrong. A test pins it.

## Field ACL is a bucket, not a filter

One flat document per Content has one visibility, but readers have many. Indexing only what an anonymous visitor may read is safe and simple, and it makes text inside an `@admins`-restricted field unfindable by anyone — including the admins it belongs to.

Instead the index stores restricted text too, in rows **grouped by the ACL expression that guards it**: one row per `(tag, distinct field-ACL)`, with public text under `acl = ''`. The overwhelming majority of Contents have exactly one row, because their fields are all public.

That is not the extension's post-filter in another costume, because the set of distinct field-ACL expressions in a wiki is _tiny_ — one per field per form, a few dozen at most, and enumerable with `SELECT DISTINCT acl`. Once per request they are evaluated with `AclService::check()`, and the passing set goes into the query as `acl IN (…)`. Filtering stays in SQL, so counts are exact and pagination is real, while restricted text remains findable by whoever may read it.

The cost is that a Content's relevance score has to be aggregated across its rows. The common case — one row, all fields public — must not pay for that.

## Fields decide what is searchable about them

The index is not built by walking the body. Some _values_ are envelope as surely as the keys are: `form_id`, timestamps, `stored_filename` UUIDs, `status`, image filenames, coordinate pairs. Indexing those reproduces a subtler form of the original bug — "search `2026`, match everything edited this year".

So each field is **asked** for its searchable text, defaulting from its field _type_, exactly as field roles default from type in ADR-0012. Prose fields contribute markup- and `{{action}}`-stripped text; email, password, file, image and map fields contribute nothing; a custom field type decides for itself. `PasswordField` already sets the precedent one layer down, flooring its own `readAccess` to `%` by type rather than by stored configuration.

This makes "email is never indexed" a property of `EmailField` rather than a list of field names — which matters, because it is not true today: the seeded `Annuaire` form ships `bf_mail` with `"read_access":"*"` under the label _"Email (n'apparaîtra pas sur le web)"_. A wiki-wide text search that returns addresses is an address-harvesting endpoint, and the field type is the only place to close that once.

### Enum fields contribute keys, and labels are translated at query time

An enum or checkbox field stores a key; a visitor searches the label. The obvious move is to index the label — and it is a trap at scale, because the label lives in the _form_, so relabelling one list item invalidates the indexed text of every entry referencing it. On a form with hundreds of thousands of entries that is an hours-long reindex triggered by a one-word edit in the designer.

So the index stores keys, and the _query_ translates the searched words into keys by consulting form definitions — which is what `SearchManager::searchWithLists()` already does, and its cost is O(forms), not O(entries). What is dropped is that method's implementation: the `body LIKE '%"propertyName":"key"%'` and checkbox regex-alternation predicates it feeds into the text search, which only ever existed because the search had no index to consult.

## Full-text indexing is mandatory, on all three dialects

For a wiki of ten thousand pages, `LIKE '%term%'` over a tag-keyed table is tens of milliseconds and would have let this ship with no dialect-specific code at all. This rewrite targets wikis of **hundreds of thousands to millions** of Contents, where `LIKE` is a multi-gigabyte scan per query that degrades linearly with growth. It was rejected on those grounds.

So MySQL gets a `FULLTEXT KEY`, PostgreSQL a generated `tsvector` with a GIN index, and SQLite an FTS5 virtual table — behind `SqlDialect`, whose "string generation only, no connection, no state" contract DDL still satisfies. SQLite is the awkward one: an FTS5 table has no secondary indexes, so the per-tag `DELETE` that every single-Content reindex performs needs an external-content table or a rowid map to avoid a scan.

SQLite gets a real implementation rather than a `LIKE` fallback specifically because **phpunit runs SQLite only**. A fallback would mean the suite exercising a query path no production wiki runs, which is the gap that let ticket 25's seven defects accumulate behind 581 green tests.

What this costs, knowingly:

- **Matching becomes token- and prefix-based, not substring.** `wiki` stops matching `yeswiki`. Every real search engine works this way — Loupe and Meilisearch are prefix engines too — but it is a visible change from today.
- **MySQL drops 1–2 character tokens** (`innodb_ft_min_token_size = 3`, a server variable requiring a restart and unsettable on shared hosting) and applies an **English** stopword list that cannot be disabled per session. `the` and `is` return nothing there and results elsewhere. This divergence is unavoidable and is simply accepted.

Stemming is deliberately not attempted yet: only PostgreSQL can do it, so enabling it would make the same query return different results per dialect and end SQLite's usefulness as a predictor of MySQL behaviour. Instead all three run as close to identically as they can — PG `'simple'`, SQLite `unicode61 remove_diacritics`, MySQL default — and terms are **prefix-matched** (`atelier*` finds `ateliers`), which covers the common plural case everywhere. Stemming, stopword lists and relevance weights are a later tuning ticket, to be steered by measurements on a real corpus.

## Staying fresh without blocking a save

Per-Content events (`page.*`, `entry.*`, `comment.deleted`, `renameTag`) reindex one tag and are trivial. Editing a **form** is not: it can invalidate every entry indexed under it, because field ACLs and the set of fields both live there.

New `form.updated` / `form.deleted` events mark those entries dirty and immediately spawn `search:reindex` through `ConsoleService::startConsoleAsync()`, under a lock so concurrent saves do not stampede. The **dirty mark is the source of truth**, not the spawn: `proc_open` is commonly disabled on shared hosting and `PhpExecutableFinder` can hand back the FPM binary — the `ASYNC_PHP_BINARY` escape hatch in that service exists because this has already bitten someone. When the spawn does not happen, the `YesWikiRuntime` maintenance hook drains the same rows in bounded batches, the way it already purges expired recovery keys. Every host converges; most converge fast.

The same drain is how an existing wiki gets its index in the first place. The migration **creates schema and marks everything dirty; it indexes nothing** — a migration that decoded a million bodies inside the upgrade request would time out, and a half-applied migration on the upgrade path is precisely the failure class ticket 25 documented. Until the drain finishes, search reports that it is building rather than falling back to the `body LIKE` query this whole decision exists to kill.

## Considered Options

- **Adopt `yeswiki-extension-fulltextsearch` as-is** — rejected. Best search quality for the least core code, and the only option offering typo tolerance today, but the index is a SQLite file on every wiki regardless of its own database, needs `ext-pdo_sqlite` and `ext-intl`, sits outside `ArchiveService` dumps, and forces approximate result counts through PHP-side ACL post-filtering.
- **Write a relational SEAL adapter** — rejected. It would give one API with Loupe and Typesense as config-level upgrades, but means owning an upstream-shaped package against a 0.x interface, and the per-dialect full-text work is the same work either way, wrapped in an abstraction core does not otherwise need.
- **Keep SEAL as a swappable seam with a DB-backed default** — rejected. The seam would be defined by what the weakest engine can do, which is the post-filtered, approximately-counted contract being replaced.
- **A `search_text` column on `pages`** — rejected. Revision-keyed table; see above.
- **`LIKE` now, dialect full-text later** — rejected on the stated scale target, though it is the right answer for a ten-thousand-page wiki.
- **Index only what an anonymous visitor can read** — rejected. Safe and much simpler, but it makes text inside a restricted field unfindable by the people entitled to it.
- **Index enum labels** — rejected. Correct excerpts and one uniform rule, at the price of an unbounded reindex on every list relabel.
- **Synchronous cascade on form save** — rejected. Never stale, and it turns saving a large form into a request that times out.

## Consequences

Search results change shape for everyone: substring matches disappear, and short words disappear on MySQL. This needs a release note, not a shim.

`SearchManager` keeps its job as the bazar entry _filter_ engine. Only the list-value translation is shared with search; its JSON-path field queries are untouched, and remain the next thing to get slow at this scale — which is what ticket 19 was filed to measure.

The index stores the text rather than only tokens, because a MySQL `FULLTEXT` index is an index _on a column_: a contentless index is not reachable there whatever SQLite permits. At a million Contents that is a few gigabytes plus the full-text index, and it is the price of the design. It does mean excerpts are computed in PHP from the index text, uniformly, instead of three ways through `ts_headline`, `snippet()` and nothing.

Acceptance requires evidence the existing suite structurally cannot produce: alongside phpunit and the MySQL e2e run, a seeded synthetic corpus with `EXPLAIN` assertions that each dialect _uses_ its full-text index. A full scan and an index scan return identical results, so nothing else distinguishes a correct index from a decorative one until the wiki is large.
