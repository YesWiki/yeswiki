# A Health check is re-derived, never recorded

Migrations write advice to the administrative log: `reportThemesStillCallingRetiredActions()`,
`reportLeftoverToolsDirectory()`, `reportFilesStillUsingFrenchNames()`,
`reportRetiredOverrides()`. Splitting that log into a Journal and a stream (ADR-0025) left them
with nowhere to go, because they are not the same kind of thing as the entries around them: a
Journal entry is an immutable fact about the past, and "your themes still call `{{searchform}}`"
is a claim about the **present** that stops being true the moment someone acts on it. We decided
findings are **not recorded at all**. They become **Health checks** — named claims re-derived
whenever they are asked — run by the migration at migrate time and re-runnable from
`/admin/health`.

## Considered Options

- **A third channel on the Journal** — rejected. It is the cheap answer and it puts a claim that
  goes stale into a record designed never to change: six months on, `/admin/logs` tells a
  webmaster to fix what they fixed in April. This is what the wiki-page log already did, and the
  reason those reports were written to a page in the first place — so a human would find them —
  is the reason they must be re-derived instead.
- **Leaving them in the migration's output only** — rejected as the whole answer, though it is
  the first phase and is already better than a page nobody opens. A finding an operator scrolled
  past during `yeswicli migrate` is gone, and the webmaster who needed it never ran the command.
- **One controller that knows every check** — rejected against ADR-0013's dependency rule. Checks
  are **declared** by the module that owns them, in the shape `ProvidesComponents` already
  established: Search declares its `ext-intl` check, Files declares bucket reachability, an
  extension declares its own.
- **Hand-writing the requirements list** — rejected. `composer.json` already is it, and
  `make binary-check` already reads it for exactly this ("assert a built binary carries every
  extension composer.json names"). Its `suggest` entries even carry each optional extension's
  consequence — *"Accent-insensitive search: without it SearchManager falls back to iconv
  transliteration, which folds fewer scripts"* — which is the sentence the screen wants. A third
  copy would rot within a release. `MINIMUM_PHP_VERSION_FOR_CORE` was a second copy that had
  already drifted (`8.2.0` against `php ^8.3`) and is deleted; `Package` reads `require.php` from
  the Program's own `composer.json` instead, through the parse it already had for packages.
- **Pass/fail** — rejected. Missing `ext-gd` is *broken*; missing `ext-intl` is *degraded, and
  here is what you lose*. Only broken raises a badge.
- **Reporting every available update** — rejected. ADR-0007 exists because core, themes and
  extensions are shared in a farm and only the designated instance may update them, and its
  amendment extends the same logic to a binary on a read-only image. A badge a webmaster cannot
  clear is permanent, and a permanent badge teaches people to ignore every badge. Update checks
  are gated on `AutoUpdateService::mayUpgrade()`, which after ticket 46 is per package — so an
  extension in the instance's own `custom/extensions/` is actionable where core is not.

## Consequences

- **Free space is not a universal question.** `disk_free_space` is in `ArchitectureTest`'s banned
  list with the ratchet at zero, and on object storage there is nothing to ask it about. By
  ADR-0022's own tiering the answer is tier-shaped: Runtime is local by necessity and is what
  fills first (SQLite, the search index, the container cache, an archive being built), so free
  space is a real number there; Public and Protected answer *reachable* instead, a state the ADR's
  amendment already made distinct from *broken*.
- **The badge is core-rendered, not attached to a configured entry.** `quick-menu.twig`'s
  `.yw-topnav-fast-access` entries come from `LayoutService::quickMenu()`, a Layout setting
  migrated out of the old `PageRapideHaut` page — a webmaster can delete their dashboard cog and
  it stays deleted. The badge sits beside `editChrome`, which core already injects into that div
  gated on `isAdmin()`, so it needs no configuration and works in any theme (ADR-0004 leaves
  themes free-form). The dashboard nav carries the same count.
- **Checks run on render**, cached briefly. "Is `ext-intl` loaded" costs nothing; "is the bucket
  reachable" costs one round trip.
- **The checks welded into migration `up()` methods have to come out** to be runnable twice. That
  is real work beyond replacing a log, and it phases: findings to stdout at migrate time first,
  the screen after.
