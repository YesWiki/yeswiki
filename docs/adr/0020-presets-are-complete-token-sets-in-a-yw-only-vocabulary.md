# Presets are complete token sets, in a `--yw-*`-only vocabulary

Core's CSS carried 737 hardcoded colour literals (196 distinct, 85 used exactly once) and 188
`border-radius` declarations (45 distinct, of which `3px`/`4px`/`5px`/`6px` were 102) across
16,203 lines in ~40 files — and no dark mode, which is not an additive feature because a
hardcoded `#fff` background cannot flip. We decided that every value core's rules consume
becomes a `--yw-*` **Design token**; that the palette collapses to a small semantic set rather
than preserving all 196 colours; that a **Preset** declares the _whole_ token set — colour,
spacing and radius — completely, in both Colour schemes, validated rather than gap-filled; and
that the pre-existing nine-variable vocabulary (`--primary-color`, `--neutral-soft-color`, …)
is **retired rather than aliased**, with custom presets migrated.

## Considered Options

- **Keep the 9 old names as the theming API and derive `--yw-*` from them** — rejected in favour
  of a single vocabulary, accepting that `custom/css-presets/*.css` must be migrated. ADR-0004
  already retires every existing theme, so third-party themes are not a compatibility surface;
  webmaster CSS under `custom/` is, and is what the migration targets.
- **Core computes the palette from a few inputs (`color-mix()` ramps, derived hover/contrast)** —
  rejected: presets author every value explicitly, so nothing about a Preset is computed. The
  cost is accepted — a preset is ~60 declarations, and a migrated preset carrying only its old
  nine values is _incomplete_, which validation flags for the webmaster to finish rather than
  core silently filling in.
- **Dark mode as a separate preset file, or as a per-wiki setting** — rejected. A Preset is
  authored **per page** (`favorite_preset` in Metadata); a Colour scheme is chosen **per viewer**.
  They are orthogonal axes, so one Preset file carries both token sets and the viewer picks
  between them; a page pins the Preset, a viewer picks the scheme, and neither overrides the other.
- **Merging all CSS into one always-loaded `yw-core.css`** — rejected: it would make ADR-0014's
  conditional declaration decorative and ship map and WYSIWYG CSS to every visitor. Files are
  instead collapsed into a handful of bundles _by load-trigger_, preserving what a given page
  downloads.
- **Migrating stored page content to drop the legacy Bootstrap vocabulary** — rejected as too
  invasive for user HTML. The 747-line shim instead shares its rules with the `yw-*` ones
  (`.yw-btn, .btn { … }`), which removes the duplication without removing the names.
- **Visual regression baselines before the refactor** — considered and declined; appearance
  changes are accepted as ordinary post-release issues for a MAJOR.

## Consequences

- Every wiki with a custom preset has work to do on upgrade: its preset is migrated to `--yw-*`
  names but is incomplete against the full token set, and is flagged until completed.
- Appearance changes on upgrade by design (196 colours become ~24 tokens), and there is **no
  visual regression testing** — 104 e2e tests assert behaviour, never appearance. An unintended
  layout break and an intended palette shift are indistinguishable to CI.
- **Design token and Layout setting are different things that look identical.** Both are `--yw-*`
  properties on `:root`; a token lives in a Preset and cascades normally, a setting is written
  inline on `<html>` by an admin screen and beats every stylesheet (ticket 30). The rule is
  one-way: if an admin screen writes it, it is not a token, and no Preset declares or is
  validated against it.
