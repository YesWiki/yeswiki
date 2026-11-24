# Core JS is vanilla JS and htmx only — no jQuery, no other framework

As part of dropping Bootstrap from core (ADR-0004), we also decided core's interactive behavior will use **only vanilla JS and htmx**, starting with this major release — no jQuery (currently used pervasively, e.g. the `tags` widget's `bootstrap-tagsinput`), and no client-side framework either. This was piloted as a live decision on the `tags` rewrite (replacing `bootstrap-tagsinput` with a live-search htmx-backed `GET /api/tags` widget) and then generalized to apply everywhere, not just that one tool.

## Considered Options

- **Keep jQuery available as a core dependency** during the transition — rejected: risks every tool rewrite independently re-deciding its JS approach, and htmx specifically was chosen because it composes with server-rendered Twig templates without requiring a client-side framework/build step.
- **Adopt a client-side JS framework instead** — not chosen; no rationale beyond htmx being judged sufficient for the interaction patterns needed (live search, form partials) was discussed.

## Consequences

Every tool absorbed into core (or rewritten while there) needs its JS audited — anything currently depending on jQuery or Bootstrap's JS components (modals, tooltips, tabs) needs an htmx or vanilla-JS replacement, not a drop-in jQuery-free polyfill.

## Amendment (ticket 16, 2026-07-27): one disclosed jQuery island

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

**Resolved (2026-11-24): core ships no client-side framework.** The lists, the
map, the calendar and the editor's component rail are drawn on the server and
kept up to date by htmx, with vanilla modules where the browser has to act
(`entry-map.js`, `entry-calendar.js`, `actions-builder.js`). A wiki writes its
own list templates in `presentations/`, and an extension adds a setting type
to the component rail as a Twig template under `actions-builder/inputs/`.
