# Drop Bootstrap from core; retire existing themes in favor of `yw-*` core styles

Core templates currently depend on Bootstrap, and the wider ecosystem has ~13 themes (several, like `yeswiki-theme-bootstrap3`, built directly on it). As part of the tools→core standardization, we decided core will ship its own minimal, `yw-*`-prefixed CSS/JS design system, and drop the Bootstrap dependency entirely.

Existing themes are expected to be **retired, not ported** — there is no compatibility shim keeping them working. New themes going forward are CSS-framework-agnostic: because core's own styles are namespaced under `yw-*`, a theme is free to bring any framework (or none) without colliding with core chrome.

## Considered Options

- **Keep Bootstrap as a core dependency, themes stay compatible** — rejected: conflicts with the broader decision (already established for this branch) that breaking changes and legacy removal are acceptable for this MAJOR version.
- **Provide a Bootstrap-compatibility layer so existing themes keep working** — not chosen; no shim is planned, existing themes are expected to not survive this rewrite.

## Consequences

Every theme in the ecosystem (`yeswiki-theme-*`) needs a maintainer decision: rewrite against `yw-*` core styles, or the theme is effectively abandoned by this major version. This is a real cost to the theme-maintaining community, not just an internal refactor — expect to communicate this before release.

## Amendment (ticket 16, 2026-07-27): a legacy-vocabulary layer, not a Bootstrap shim

When the removal actually landed, one nuance surfaced that the original "no
compatibility shim" wording did not anticipate: **stored wiki content** (page
bodies, actions-builder output accumulated over years) carries Bootstrap class
names (`btn`, `alert`, `label`, `row`/`col-*`, …) that no template rewrite can
reach. `styles/yw-core.css` therefore ends with a "Legacy Bootstrap vocabulary"
section styling those bare class names with the `yw-*` look.

This is **not** the rejected Bootstrap-compatibility layer: Bootstrap (the
library, its JS, its grid/theme system) is fully removed and themes still
cannot rely on it. It is core taking ownership of a small set of legacy class
names present in user content, styled by core's own CSS. Two consequences:

- These bare selectors are an exception to the "everything namespaced under
  `yw-*`" rule; a theme shipping its own `.btn`/`.alert` rules will interact
  with them (by design — the theme wins on specificity/order, as with any
  content styling).
- New core code must use `yw-*` classes; the legacy vocabulary is explicitly
  marked "not an API to build on". Existing templates keep legacy names only
  where rewriting them adds churn without benefit.
