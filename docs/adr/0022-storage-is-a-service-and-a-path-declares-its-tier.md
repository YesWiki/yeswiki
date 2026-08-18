# Storage is a service, and a path declares its tier

YesWiki writes to disk from about 180 places and reads from about 370, addressed as bare
relative paths, which is why an instance must own a writable local volume and cannot be run
against object storage at all. We decided that **every file YesWiki's own code touches goes
through one `Storage` service**, backed by [Flysystem](https://flysystem.thephpleague.com/),
addressed by the instance-relative paths the code already used — and that **the path decides
the tier**: Public, Protected or Runtime. Data tiers are pluggable between a local directory
and an S3-compatible bucket; Runtime is local by necessity and refuses object storage at boot.

## Considered Options

- **One backend for everything, including the compiled container and the database** — rejected,
  and this is the decision the whole ADR turns on. SQLite and the Loupe search index need POSIX
  locking and seeks; `cache/container/`, `cache/templates/`, `cache/routes/` and
  `custom/extensions/` are PHP that gets `include`d; the maintenance and reindex locks need
  atomic create. S3 offers none of that. Pretending the tier is a mere configuration detail
  would mean an instance that boots, appears configured, and corrupts its index. Naming
  **Runtime** as a tier that _cannot_ be remote turns that from a silent failure into a refusal
  with the offending path in the message.
- **Naming the tier at the call site**, either as a Flysystem `MountManager` scheme
  (`public://custom/fonts/x`) or as three injected services — rejected. In this codebase the
  path already tells you the tier: `private/` is protected by definition, `custom/` is public by
  definition. Saying it a second time creates a second source of truth, and
  `protected://custom/fonts/x` is a writable nonsense. A declared prefix table is the one place
  that knowledge lives, an unknown prefix throws, and the ~550 call sites keep their strings.
- **Serving every byte through PHP** — rejected for Public. An instance with no persistent disk
  exists to be cheap and horizontally scalable; booting the wiki to hand over a woff2 makes S3
  slower than the disk it replaced. Public paths get their URL rewritten to the bucket or CDN
  and the wiki is not in the request path at all. Protected paths are the opposite case and keep
  streaming through the ACL-checked route — they are never given a URL, because a URL is exactly
  what an access check exists to withhold. Presigned URLs were rejected for both: a signature
  outlives nothing, and a cached page's HTML outlives the signature.
- **A stream wrapper (`yeswiki://`) instead of leases** — rejected. Four libraries need a real
  path (`ZipArchive`, `Zebra_Image`, `HTMLPurifier::cleanFile`, `getimagesize`), and a wrapper
  serves three of them: `ZipArchive` ignores stream wrappers. That is a feature which works on
  every developer's laptop and fails only on the deployment nobody can debug. `withLocalCopy()`
  and `withLocalTarget()` are explicit, cost nothing on a local tier, and put the download and
  upload where a reader can see them.
- **`aws/aws-sdk-php`** — rejected on measurement: 67 MB and 31 packages, against 3.9 MB and 24
  for `async-aws/s3`, on a `vendor/` that is 97 MB today. Both speak the same S3 API to minio,
  Garage, Scaleway, OVH, Wasabi, R2 and B2, which is the compatibility that actually matters.
- **Deriving each farm instance's key prefix from its directory name** — rejected. Renaming or
  moving an instance directory would silently orphan every file it owns. The bucket is the
  instance's data root and a shared bucket takes an explicit prefix, the same rule the config
  file already follows: what an instance uses is what its config says, never what its path
  implies.
- **Returning false everywhere instead of throwing** — rejected. It is what the code does today
  (45 `@`-silenced or unchecked calls) and it is why a failed write is currently invisible.
  Writes throw; only a **derived artefact** — a thumbnail, a cached feed image, a published
  asset — may fail quietly via `storeDerived()`, because the caller can serve it once and make
  it again.

## Consequences

- An instance can run with no writable data volume beyond a small Runtime directory, which is
  what makes a container deployment and a read-only root filesystem possible.
- Fonts and stylesheets served from a bucket are cross-origin: the bucket needs CORS, and that
  is a deployment step no local install ever needed.
- Existing installs move with `./yeswicli storage:sync`, which is re-runnable and reports what it
  copied. Configuring a bucket without running it changes nothing — the operator picks the
  moment.
- The rule is enforced by a seeded ratchet in `ArchitectureTest` that may only shrink, in the
  shape the phpstan baseline and `KNOWN_VIOLATIONS` already use. The bootstrap, the composer
  scripts and the Storage implementation itself are permanently allowed: they run before, or
  without, a container.

## Amendments

**A bucket that does not exist answers "no", not "boom" (2026-08-18, ticket 42).** The first
thing a wiki does with object storage is ask whether a file is there — `ThemeManager` asks about
`custom/themes/…` before anything renders. On a bucket nobody has created yet that is an error
rather than a "no", and it happens early enough that no command can run to fix it, including the
one whose job is to create the bucket. So the three existence questions (`exists`,
`fileExists`, `directoryExists`) treat a missing bucket as an empty one; every read, write, move
and delete still throws, and the message names the bucket and the command that makes it. A wiki
pointed at a bucket that is not there therefore boots, serves no files, and says so the moment
anything is written — rather than refusing to start with no way forward.

**Public objects are written `public-read`, but the bucket policy is what actually decides
(2026-08-18, ticket 42).** Storage sets the visibility on Public writes, which is what AWS,
Scaleway and OVH honour. Gateways that ignore per-object ACLs (SeaweedFS among them) need the
policy instead, and since the tier is in the path, that policy is expressible: allow anonymous
`GetObject` on `custom/*`, `files/*` and `cache/*`, and nothing else. `docs/fr/admin.md` carries
it, next to the CORS rule fonts need.

**Backups are Protected, and the folder stopped being a setting (2026-08-18, ticket 42).** The
open question -- where does an instance with no disk keep its backups -- was answered by looking
at what wikis actually do: they all use `private/backups`. So `archive[privatePath]` is gone, a
migration removes it, and `ArchiveService` goes through Storage: an archive is built in a lease
and stored under `private/backups`, which means the bucket when there is one, and it is streamed
back through the ACL-checked route rather than given a URL.

What is left local during an archive is Runtime by the same rule as everything else in that tier:
the progress files a web request polls while a separate process writes them, now in
`cache/archive/` rather than beside the backups, and the zip being built, which `ZipArchive` can
only produce as a real file.
