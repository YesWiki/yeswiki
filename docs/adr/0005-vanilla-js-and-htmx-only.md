# Core JS is vanilla JS and htmx only — no jQuery, no other framework

As part of dropping Bootstrap from core (ADR-0004), we also decided core's interactive behavior will use **only vanilla JS and htmx**, starting with this major release — no jQuery (currently used pervasively, e.g. the `tags` widget's `bootstrap-tagsinput`), and no adoption of a heavier framework (Vue/React/Alpine) either. This was piloted as a live decision on the `tags` rewrite (replacing `bootstrap-tagsinput` with a live-search htmx-backed `GET /api/tags` widget) and then generalized to apply everywhere, not just that one tool.

## Considered Options

- **Keep jQuery available as a core dependency** during the transition — rejected: risks every tool rewrite independently re-deciding its JS approach, and htmx specifically was chosen because it composes with server-rendered Twig templates without requiring a client-side framework/build step.
- **Adopt a JS framework (Vue/React/Alpine) instead** — not chosen; no rationale beyond htmx being judged sufficient for the interaction patterns needed (live search, form partials) was discussed.

## Consequences

Every tool absorbed into core (or rewritten while there) needs its JS audited — anything currently depending on jQuery or Bootstrap's JS components (modals, tooltips, tabs) needs an htmx or vanilla-JS replacement, not a drop-in jQuery-free polyfill.
