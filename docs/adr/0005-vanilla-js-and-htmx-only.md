# Core JS is vanilla JS and htmx only — no jQuery, no other framework

As part of dropping Bootstrap from core (ADR-0004), we also decided core's interactive behavior will use **only vanilla JS and htmx**, starting with this major release — no jQuery (currently used pervasively, e.g. the `tags` widget's `bootstrap-tagsinput`), and no adoption of a heavier framework (Vue/React/Alpine) either. This was piloted as a live decision on the `tags` rewrite (replacing `bootstrap-tagsinput` with a live-search htmx-backed `GET /api/tags` widget) and then generalized to apply everywhere, not just that one tool.

## Considered Options

- **Keep jQuery available as a core dependency** during the transition — rejected: risks every tool rewrite independently re-deciding its JS approach, and htmx specifically was chosen because it composes with server-rendered Twig templates without requiring a client-side framework/build step.
- **Adopt a JS framework (Vue/React/Alpine) instead** — not chosen; no rationale beyond htmx being judged sufficient for the interaction patterns needed (live search, form partials) was discussed.

## Consequences

Every tool absorbed into core (or rewritten while there) needs its JS audited — anything currently depending on jQuery or Bootstrap's JS components (modals, tooltips, tabs) needs an htmx or vanilla-JS replacement, not a drop-in jQuery-free polyfill.

## Amendment (ticket 16, 2026-07-27): one disclosed jQuery island, and the Vue boundary

The removal landed with a single, bounded exception: the bazar form-designer
admin page (`javascripts/form-edit-template/`, built on the vendored jQuery
`formBuilder` plugin, which has no vanilla equivalent) keeps jQuery,
self-loaded by `templates/forms/forms_form.twig` only — no other page loads
it, and no Bootstrap file is loaded even there (the plugin's hardcoded `btn*`
class names are styled by a scoped CSS shim). Ticket 26 tracks replacing the
plugin and removing jQuery entirely.

**Resolved (ticket 26, 2026-07-27): the island is gone.** The form designer
was rewritten as a vanilla ES module (`javascripts/form-builder/`) editing
the JSON form template directly; `javascripts/form-edit-template/`, the
vendored `formBuilder`/`formbuilder-languages`/`jquery`/`jquery-ui-sortable`
packages, and the scoped CSS shim were all deleted, along with the last
stray `$.getJSON`/`$.extend`/`$.param` utility calls (replaced by `fetch`
and `deepMergeParams`/`serializeSearchParams` in `javascripts/url.js`).
Core ships zero jQuery, with no exceptions.

The pre-existing Vue-based "dynamic entries index" subsystem (BazarTable/
DynTable/BazarMap/BazarCalendar and the actions-builder app) was grandfathered
as-is in ticket 24 (user-confirmed boundary) and stays: this ADR governs new
core JS, which remains vanilla + htmx; the Vue island neither grew nor was it
ported to something heavier — its jQuery/DataTables usage was removed like
everywhere else.
