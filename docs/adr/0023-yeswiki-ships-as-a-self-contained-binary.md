# YesWiki ships as a self-contained binary

Installing YesWiki means having PHP 8.3 with a dozen extensions, Composer, a webserver with the
right rewrite rules and a database, which is why most of its users are on shared hosting they did
not choose and cannot leave. We decided YesWiki also ships as **one file for Linux**, x86_64 and
arm64, built on [FrankenPHP](https://frankenphp.dev/), containing the PHP interpreter, Caddy and
the whole application, and that this becomes **the recommended way to run a wiki** without
retiring php-fpm, Apache shared hosting or the Docker stack.

The split that makes it possible already existed. `src/bootstrap_paths.php` has separated
read-only code from writable wiki data since the farm work, and ticket 41's `Storage` sorted every
writable path into Public, Protected and Runtime (ADR-0022). What was missing was a name and a
home for the code half. It is now the **Program**: one versioned read-only tree serving any number
of **Instances**, written out under `YESWIKI_PROGRAM_ROOT` (`~/.local/share/yeswiki/` by default)
as `program-<version>/`. `YESWIKI_SOURCE_DIR` is renamed `YESWIKI_PROGRAM_DIR` to match, because
`Source` already means a palette entry that supplies Items and very nearly means `Data source`.

## Considered Options

- **FrankenPHP's own `EMBED`**, the documented way to do this, is rejected and it is the decision
  everything else hangs off. `embed.go` unpacks the application into
  `$TMPDIR/frankenphp_<checksum>` at startup and never removes it: the path changes with every
  release, `/tmp` must be writable, systemd's `ProtectTmp` moves it underneath you, and a `/tmp`
  sweeper can delete the source tree of a wiki that is currently serving. That is
  [issue #2308](https://github.com/php/frankenphp/issues/2308), open, unassigned, with no
  maintainer answer. It is also ADR-0022's rejected option in a different costume: an instance
  that boots, appears configured, and breaks in a way the bug report cannot describe. Core carries
  the tree in `embed.FS` itself and writes it once at `setup`, which additionally buys stable
  absolute paths for opcache and a boot that does not unpack a tar.
- **Pinning `EmbeddedAppPath` at build time** with `-ldflags`, the only override FrankenPHP
  offers, is rejected because it is baked in: one path for every user of that binary. A person
  running a wiki in their home directory cannot write to `/var/lib/yeswiki`, which is the audience.
  Naming the *root* at run time (`--program-root`, `YESWIKI_PROGRAM_ROOT`) covers the laptop and
  the image build with one rule, and it also answers the systemd service running as a user with no
  `HOME`.
- **Waiting for the upstream fix** is rejected as a plan, though the patch is still worth sending.
  Nobody is on #2308 and it needs a fallback regardless.
- **Accepting FrankenPHP's command surface**, `./yeswiki php-server` and
  `./yeswiki php-cli yeswicli setup`, is rejected. It costs no Go and no build machinery, and it
  is unusable by the only audience that justifies a binary. `XCADDY_ARGS` takes custom Caddy
  modules, and Caddy lets a module register top-level commands, so `setup`, `serve` and `upgrade`
  are reachable for a few hundred lines of Go. `php-server` and `php-cli` stay exposed, because a
  binary that owns its whole surface owns every escape hatch too, and things go wrong in the field.
- **A stock FrankenPHP deployment with the sources on disk** is rejected as an answer to this
  problem, not as a bad idea. It is a smaller job with real benefits, and it changes nothing for
  the person who cannot install PHP.
- **The Program inside the Instance directory** is rejected. "Your wiki is one folder, copy it to
  back it up" is a good story, and it costs a full `vendor/` per wiki, puts code inside a tree the
  entire storage model treats as data, and makes every backup carry a tree the binary already
  contains. Shared and keyed by version is what `YESWIKI_SOURCE_DIR` always meant.
- **Distributing the binary through GitHub releases** is rejected, though that is where CI builds
  it. ADR-0016 made `repository.yeswiki.net` the only distributor eight months ago; adding GitHub
  would give a wiki two trust roots and break the `yeswiki_repository` override that private and
  air-gapped mirrors depend on. Releases are published *into* that host instead.
- **Trusting TLS alone for updates** is rejected: it puts every wiki running the binary inside the
  blast radius of one server. Binaries are signed with an ed25519 key whose public half is
  compiled in, so verification is offline and needs no PKI.
- **Detecting a container to disable self-update** is rejected. `/.dockerenv`, cgroup shapes and
  `KUBERNETES_SERVICE_HOST` are all heuristics that have been wrong, and all three answer wrongly
  for a writable container somebody genuinely wants to self-update. **Whether the Program root is
  writable** is the real question and answers itself: the laptop upgrades, the read-only image
  refuses and names the path.
- **macOS and Windows** are rejected for now. macOS builds only "mostly static", carries a
  documented `pdo_pgsql` connection caveat, and needs an Apple Developer account at $99 a year
  forever or Gatekeeper blocks the download. Windows got native support in
  [PR #2119](https://github.com/php/frankenphp/pull/2119), but it links against the official PHP
  builds, so the artefact there is not one file: it is a second deployment model with the same
  name. Linux is where wikis are served, and arm64 covers the Raspberry Pi and cheap ARM VPS tier
  that associations actually buy.
- **A minimal extension set** is rejected. A fully static musl build cannot load an extension
  afterwards, ever, so the cost of leaving one out is that the feature is permanently gone on this
  deployment. Everything used ships: the `composer.json` list, plus `ext-openssl`, which
  `HttpSignatureService` and `KeyPairGenerator` have always used and which nothing declares, plus
  `pdo_sqlite`, `pdo_mysql` and `pdo_pgsql`, plus opcache, plus `ext-imap` for ticket 28's
  importer. The undeclared `openssl` is the argument in miniature: `composer.json` was never a
  trustworthy manifest, so the list comes from an audit.

## Consequences

- **A signing key exists and somebody owns it for years.** Losing it strands every installed
  binary on its current version; leaking it owns them all. This is a new operational
  responsibility for the project, not a build step.
- **Which Instance is meant stops being inferred.** The Go command resolves the path argument,
  defaulting to the working directory, and states `YESWIKI_INSTANCE_DIR`. `bootstrap_paths.php`
  reads it from the environment before falling back to `getcwd()`, which under an application
  server is not where anybody launched anything.
- **The Instance directory is the docroot**, with an `index.php` written by `setup` exactly as
  `core:create-instance` writes one today. The Program is never web-reachable: `AssetPublisher`
  already publishes into `cache/assets/{version}/`, and `custom/`, `files/` and `cache/` are all
  Instance-local.
- **The webserver rules become a Caddyfile that core ships**, overridden by `<instance>/Caddyfile`
  when one exists. Three rules in `docker/nginx.conf` have no equivalent yet and must survive: the
  ActivityPub actor rewrite, the webfinger redirect, and the `private/` deny. The last is
  redundant given ADR-0022 never gives a Protected path a URL, and serving `private/yeswiki.db`
  over HTTP is not a mistake worth making once.
- **`PackageTheme` has to stop writing into the Program.** `PackageTool::localPath()` already
  falls back to `custom/extensions/` when the Instance differs from the Program, and
  `custom/themes/` is already a first-class root that `ThemeManager`, `PresetService` and
  `ConfigurationAction` all read. Only the theme installer still hardcodes the Program.
  `UpgradeCommand` also gates on `isDesignatedUpdateInstance()` before looking at its package
  argument, so `yeswicli upgrade some-extension` refuses on any farm instance today. Both are
  bugs the binary exposes rather than causes.
- **`ArchiveService.php:789` breaks and must be fixed with the rename.** It refuses to run unless
  `index.php`, the config file, `composer.json` and `composer.lock` are all in the working
  directory. Once the Program splits off, the last two are not, and backups stop.
- **The binary is large.** With `--no-dev` the tree is roughly 77 MB, of which `javascripts/vendor`
  is 26 MB and `vendor/` about 39 MB once phpstan, phpunit and php-cs-fixer are gone. Plus a
  static interpreter and Caddy, expect something in the 120 to 180 MB range before `COMPRESS=1`.
  That is ordinary for this class of tool and not worth optimising by dropping features.
- **SQLite runs in WAL mode with a busy timeout**, set at `setup`, because worker mode puts
  several threads on one file. It is the default database for the zero-install case, so this is
  the common path and not an edge.
- **One replica.** See CONTEXT.md's decision on horizontal scaling: sessions, the hashcash secret,
  the maintenance lock and extension distribution are all node-local, and each is its own piece of
  work in its own spec.
