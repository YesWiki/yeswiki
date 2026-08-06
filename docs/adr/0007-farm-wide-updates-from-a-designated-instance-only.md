# Farm-wide updates trigger only from an admin-designated instance

`autoupdate`'s current path resolution is already inconsistent with itself: `PackageTool`/`PackageTheme` target a source-tree-relative path (ignoring `YESWIKI_SOURCE_DIR`), while `PackageCore` targets the currently-executing script's directory — the _instance_ dir in a farm setup. The bug is invisible on standalone installs (source dir == instance dir there) but would silently target different places on a farm.

We decided core/tools/theme updates consistently target the shared `YESWIKI_SOURCE_DIR` (one update affects every farm instance at once, matching how source-side code is already shared), and that **only an admin-designated instance can trigger a farm-wide update** — not any instance independently.

## Considered Options

- **Any instance can trigger an update to the shared source** — rejected: every farm instance's admin panel would offer an "update" button that mutates shared state for the whole farm, inviting update races or an unprivileged/less-trusted instance owner accidentally updating everyone else's shared code.

## Consequences

The farm needs a concept of "the designated update-triggering instance" (config or convention still to be defined) — this isn't just a path fix, it's a new authorization boundary that didn't exist before (today every instance is symmetric with respect to autoupdate).
