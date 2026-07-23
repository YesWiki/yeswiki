# Reconcile `doryphore-dev` into `ectoplasme` before implementation starts

Checking the gap the other direction from the usual `doryphore-dev..ectoplasme` comparison surfaced 65 commits on `doryphore-dev` not present on `ectoplasme`, over a dozen tagged `GHSA-*` security-advisory fixes. The heaviest-touched paths overlap directly with tools already redesigned this session (`tools/bazar`, `tools/templates`, `tools/login`, `tools/attach`, `tools/tags`, `tools/contact`, `tools/rss`) and with `includes/` generically — which `ectoplasme`'s own `refacto: src instead of includes` commit renamed to `src/`, so a merge has real rename-conflict work to do, not just a fast-forward.

We decided to **reconcile fully before any core-absorption implementation begins**, rather than cherry-picking just the security fixes or deferring reconciliation to the end.

## Considered Options

- **Cherry-pick only the `GHSA-*`-tagged commits now, defer the rest** — rejected: cheaper upfront, but risks missing fixes that don't cherry-pick cleanly across the rename, and leaves an ongoing "which fixes did we port" tracking burden.
- **Defer reconciliation entirely, verify at the end** — rejected: the highest-risk option — every tool redesigned this session (attach, tags, login, contact, security, bazar, templates) could end up quietly reintroducing a vulnerability `doryphore-dev` already fixed, discovered only in a large, late reconciliation pass.

## Consequences

Implementation on the core-absorption plan (this session's `CONTEXT.md`/ADRs) doesn't start until this merge lands. Expect the merge itself to require deliberate rename-aware conflict resolution across `includes/` → `src/`, not a quick `git merge`.
