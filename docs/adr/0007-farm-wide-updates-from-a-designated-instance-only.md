# Farm-wide updates trigger only from an admin-designated instance

`autoupdate`'s current path resolution is already inconsistent with itself: `PackageTool`/`PackageTheme` target a source-tree-relative path (ignoring `YESWIKI_SOURCE_DIR`), while `PackageCore` targets the currently-executing script's directory — the _instance_ dir in a farm setup. The bug is invisible on standalone installs (source dir == instance dir there) but would silently target different places on a farm.

We decided core/tools/theme updates consistently target the shared `YESWIKI_SOURCE_DIR` (one update affects every farm instance at once, matching how source-side code is already shared), and that **only an admin-designated instance can trigger a farm-wide update** — not any instance independently.

## Considered Options

- **Any instance can trigger an update to the shared source** — rejected: every farm instance's admin panel would offer an "update" button that mutates shared state for the whole farm, inviting update races or an unprivileged/less-trusted instance owner accidentally updating everyone else's shared code.

## Consequences

The farm needs a concept of "the designated update-triggering instance" (config or convention still to be defined) — this isn't just a path fix, it's a new authorization boundary that didn't exist before (today every instance is symmetric with respect to autoupdate).

## Amendments

**A binary is the designated updater of itself, and writability is the test (2026-08-21,
ADR-0023).** `AutoUpdateService::isDesignatedUpdateInstance()` implements this ADR as
`realpath(instanceDir) === realpath(sourceDir)`, which is right for a farm and accidentally right,
for entirely the wrong reason, for the self-contained binary: a binary's **Program** is never its
**Instance**, so the check refuses, and the operator is told to go and ask the designated instance
of a farm that does not exist.

The binary updates itself, so the rule it needs is a different one: **it may do so when it can
write the Program root**, which is a fact about the deployment rather than a claim the deployment
makes. A laptop's `~/.local/share/yeswiki` is writable and upgrades. A container image's read-only
`/opt/yeswiki` refuses and names the path, which is correct, because there you roll the image.
Container detection was considered and rejected in ADR-0023: every available signal is a heuristic
that answers wrongly for a writable container somebody genuinely wants to self-update.

This also exposed two bugs this ADR's check caused and the binary merely revealed. **Both are
fixed (2026-08-21, ticket 46).**

`UpgradeCommand::execute()` tested `isDesignatedUpdateInstance()` before it looked at its
`package` argument, so `yeswicli upgrade some-extension` was refused on every farm instance, even
though `PackageTool::localPath()` would have installed that extension into the instance's own
`custom/extensions/` perfectly well. And `PackageTheme::localPath()`, unlike `PackageTool`,
targeted the Program unconditionally, so no farm instance could install a theme of its own either,
despite `custom/themes/` already being a root that `ThemeManager`, `PresetService` and
`ConfigurationAction` all read.

**The gate is per package, not per instance**: `AutoUpdateService::mayUpgrade()` asks whether this
package is core, and only then whether this is the designated instance. That is what the rule
above always meant -- the authority is needed because a core update mutates code every instance
runs, and an instance-local extension is not that. `PackageTheme` now mirrors `PackageTool`:
the Program tree when Instance and Program are the same directory, `custom/themes/{name}/`
otherwise. The admin screen carries the same distinction, so an instance is no longer shown an
update button that refuses.

Finally, `upgrade` splits: fetching, verifying and swapping the executable is separable from
running migrations, so an image deployment can run migrations as a job while the executable
arrives with the image.
