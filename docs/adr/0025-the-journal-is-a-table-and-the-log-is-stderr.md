# The Journal is a table, and the log is stderr

YesWiki's audit trail is a wiki page. `AdministrativeLogService` appends a French sentence with
wiki markup to `LogDesActionsAdministratives{Ymd}`, one page per day, pruned to its last ten
revisions by a raw `DELETE`, at the cost of a full `PageManager::save()` per logged event. Two
places already know it is not really content and exclude it by `LIKE`
(`PageApiController:40`, `TagsManager:299`). There is no logger of any kind beside it: `psr/log`
is present only transitively, no Monolog, no `set_exception_handler`, and `YesWikiInit.php:569`
merely flips `display_errors` on in debug.

We decided to split it in two. **What must survive becomes the Journal** — a table in the wiki's
own database, structured, filterable, retained in months, shown at `/admin/logs`. **What must be
readable when the database is not becomes the log** — PSR-3 to stderr, JSON lines, collected by
whatever runs the process. Both halves are written from one call site, and every event reaches
stderr first and unconditionally, so the Journal insert may fail silently without losing the
event.

## Considered Options

- **Triples** — rejected, and the project already reversed this exact move. `triples` is
  `(resource, property, value)` with indexes on the first two and `value` unindexed `TEXT`; an
  audit query filters by actor, action, target and date range, so every one of those is a `LIKE`
  scan or a self-join, and with no time column retention becomes string comparison. Ticket 27 took
  the Content *type* out of triples for these reasons in its own words — "a row's own type is
  stored in a different table from the row, so every query that needs it carries a join … a type
  filter that cannot use an index is the wrong shape". `TripleStore` also memoises in
  `cacheByResource` and `matchingCache`, built for a small set of facts read many times, which is
  the opposite of an append-only log.
- **Rows in `pages`** — rejected, and it is the tempting one: `type` and `time` are indexed,
  `user` is the actor, `parent` could be the target. But ADR-0001 makes tags globally unique
  across Content types, so every event would consume a tag from the namespace a webmaster names
  pages in. Worse, it repeats today's mistake at scale: the current design already forced two
  `NOT LIKE` exclusions, and rows in `pages` would mean teaching every consumer — recent changes,
  the search indexer, revision lists, tag resolution, backups — to exclude a type.
- **A file under `private/` or `cache/`** — rejected on ADR-0022's own table. `cache/` is the
  **Public** tier and its bucket policy allows anonymous `GetObject` on `cache/*`, so the audit
  trail would be published. Bare `private/` is not in `Storage::TIERS` at all and an unknown
  prefix throws; declaring it Protected puts it in a bucket. And no tier can append: `Storage` has
  `write`/`writeStream`/`read`/`copy`/`delete` and no `append()`, because Flysystem has none,
  because object stores cannot. Bypassing Storage is not available either —
  `ArchitectureTest::KNOWN_VIOLATIONS` is `[]` and the ratchet may only shrink. Two quieter
  reasons: Runtime is the tier explicitly not backed up, and Farm ticket 02 puts N wikis in one
  FrankenPHP worker, so appends above `PIPE_BUF` would need locking the database gives free.
- **One store, one retention** — rejected. The two populations share columns and nothing else.
  Measured on a real eight-year wiki, content acts run ~4,000 a year (`hpf`: 3,651 in 2025, 4,461
  in 2024, busiest day ever 267), so 365 days of audit is a few thousand rows; a year of
  diagnostics is noise nobody reads. `journal_audit_purge_time` is 365 and
  `journal_diagnostic_purge_time` is 14, pruned in the existing `maintenance()` pass beside
  `purgePages()`, which is already a locked, half-hourly housekeeping sweep with a
  retention-in-days setting defaulting to the same 365.
- **A centralised journal for a farm** — rejected as a regression. first-class-binary ticket 01
  bounds a compromised wiki by credentials — "each wiki reaches its own and is refused on its
  neighbour's" — and a shared journal database hands that straight back: a wiki that could not
  read its neighbour's pages could read its neighbour's admin history. The stream is central
  instead, which is free because the farm is one process; each line carries the wiki's
  `getBaseUrl()` minus its scheme, which keeps a subdirectory install distinct and follows
  ADR-0022's rule that an instance is identified by what its config says, never by its path.
- **Excluding content create/update, because `pages` already versions them** — rejected on
  measurement after being recommended on a guess that was an order of magnitude out. At 4,000
  rows a year the duplication is affordable, and it buys a question `pages` cannot answer: there
  is no index on `user`, so "what did this person do last Tuesday" is a full table scan today.
  The entry records the **act** and never the content — no diff, no body — so the two can never
  disagree.
- **Monolog** — rejected for the easy half. Two sinks are an `fwrite` of `json_encode` and one
  upsert; the hard half is bespoke and Monolog supplies none of it. The seam is typed
  `LoggerInterface` so an operator wanting Sentry or Loki can substitute one.
- **Deleting the 443 legacy log pages outright** — rejected. An upgrade that silently destroys an
  audit trail is the one thing an audit system exists to prevent. They are imported as
  `action='legacy'` entries with `at` and actor parsed and the French sentence kept verbatim, and
  `journal_audit_purge_time` decides what survives — a policy the operator can raise before
  migrating, rather than a decision the migration made for them.

## Consequences

- **Errors are in the write path of the database**, which is exactly the case they describe. That
  is survivable only because stderr is written first and unconditionally; the insert is
  best-effort in the shape ADR-0022 already named `storeDerived()`.
- **A storm must cost one row.** Diagnostics are unique on `(fingerprint, day)`, where the
  fingerprint is `hash(channel, level, class, file, line)` and deliberately **not** the message —
  messages carry variable data, so fingerprinting on them defeats the dedup exactly when it is
  needed. A repeat updates `last_at` and increments `repeat`; `at` stays the first sighting,
  because a counter without both ends cannot tell a fixed bug from a raging one. Audit entries
  never collapse: three deletions are three facts, and 500 failed logins are 500 source addresses.
  A per-day ceiling and a recursion guard sit on top, because the Journal must never be why a wiki
  falls over.
- **No stack trace is stored anywhere**, in the database or on stderr — only type-only frames.
  Plaintext credentials in aggregated logs is the canonical logging failure and its vector is
  always a trace or a request dump shipped somewhere with a different access model. In a farm that
  is sharper: one stream carries every wiki and the operator is not the data controller for each.
  The cost is real — a webmaster on shared hosting sees what broke and where, and cannot
  self-diagnose a deep one.
- **`/admin/logs` shows one stream whose two halves have different memories** — everything for a
  fortnight, audit alone beyond it. That needs one line of text at the boundary, or it reads as
  errors having been hidden.
- **443 tags come back** and the two `NOT LIKE` exclusions leave `PageApiController` and
  `TagsManager`.
- **A migration's advice stops being logged at all.** A finding is a claim about the present and
  goes stale; an immutable record is the wrong home, which is why "your themes still call retired
  actions" could sit in a wiki page for years after someone fixed it. Those checks become
  re-derivable Health checks — see ADR-0026.

## Amendments

**The Journal's DDL lives on `SqlDialect`, not in the installation template (2026-08-26, ticket
51).** The plan said `installation-create-tables.sql.twig`, and that would have meant the table's
shape existing twice: the installer renders the twig, and a migration cannot. `SearchIndexSchema`
had already settled this for the search index — one `journalDdl()` per dialect, called by
`InstallationService` on a fresh wiki and by `JournalSchema::create()` on an upgrade — and
`JournalSchema` is its twin.

**Diagnostics are pruned on `last_at`, audit on `at` (2026-08-26, ticket 51).** Retention was
stated once, for both halves, over the same column. But a diagnostic row is not a fact about the
past: `at` is when a fault was *first* seen, and a fault first seen thirteen months ago and still
firing this morning is the one an operator most wants on the screen. Pruning it on `at` would
delete exactly that, and the next occurrence would recreate it with a fresh `at` — losing the only
thing the pair of timestamps exists to say. Audit entries, which are facts about the past and never
collapse, are pruned on `at` as planned.

**There is no `message` column; the message is a key in `context` (2026-08-26, ticket 51).** The
column table never had one, and "store the latest message" reads like it did. Keeping the message
inside the JSON is what lets a legacy import carry a French sentence verbatim under the same key an
exception's text uses, with no column that is empty for every audit row.

**The per-day ceiling counts fingerprints, not writes (2026-08-26, ticket 51).** Counting writes
would have made a storm of *one* fault trip its own ceiling and stop incrementing its own counter —
turning the acceptance criterion ("5,000 throws produce one row with `repeat = 5000`") into a row
stuck at 500. A fingerprint already stored today always goes through; only a *new* one counts
against the ceiling, which is what "dedup bounds repeats, not distinct fingerprints" actually
requires.
