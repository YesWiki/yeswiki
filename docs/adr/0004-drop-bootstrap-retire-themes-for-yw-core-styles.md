# Drop Bootstrap from core; retire existing themes in favor of `yw-*` core styles

Core templates currently depend on Bootstrap, and the wider ecosystem has ~13 themes (several, like `yeswiki-theme-bootstrap3`, built directly on it). As part of the tools→core standardization, we decided core will ship its own minimal, `yw-*`-prefixed CSS/JS design system, and drop the Bootstrap dependency entirely.

Existing themes are expected to be **retired, not ported** — there is no compatibility shim keeping them working. New themes going forward are CSS-framework-agnostic: because core's own styles are namespaced under `yw-*`, a theme is free to bring any framework (or none) without colliding with core chrome.

## Considered Options

- **Keep Bootstrap as a core dependency, themes stay compatible** — rejected: conflicts with the broader decision (already established for this branch) that breaking changes and legacy removal are acceptable for this MAJOR version.
- **Provide a Bootstrap-compatibility layer so existing themes keep working** — not chosen; no shim is planned, existing themes are expected to not survive this rewrite.

## Consequences

Every theme in the ecosystem (`yeswiki-theme-*`) needs a maintainer decision: rewrite against `yw-*` core styles, or the theme is effectively abandoned by this major version. This is a real cost to the theme-maintaining community, not just an internal refactor — expect to communicate this before release.
