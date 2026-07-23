# `attach` download enforces the page's read ACL by default

Today, `attach`'s download path (`attach::doDownload()`) performs **no ACL check at all** — upload requires `write` access to the owning page, but any uploaded file is fetchable by anyone who has (or guesses) its URL, regardless of that page's read ACL. As part of consolidating `attach` upload/download into a single API-route-backed service, we decided the new download route will **enforce the owning page's read ACL by default**, with a per-file/per-field opt-out flag to keep public embedding (e.g. images embedded in public pages, links shared outside the wiki) working where it's actually wanted.

## Considered Options

- **Leave download unauthenticated by URL, as today** — rejected: silently preserves a real access-control gap into the rewrite, when the rewrite is already touching this exact code path.
- **Enforce ACL with no opt-out** — rejected: would break existing legitimate public-embedding use cases with no escape hatch.

## Consequences

This is a breaking behavior change for existing installs on upgrade (once the later migration script exists): file URLs that were previously fetchable by anyone become access-controlled unless explicitly flagged public. Needs to be called out clearly in upgrade communication, not just in this ADR.
