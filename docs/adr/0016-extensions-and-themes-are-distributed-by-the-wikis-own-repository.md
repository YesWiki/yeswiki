# Extensions and themes are distributed by the wiki's own repository, not by Composer

YesWiki declared two Composer package types — `yeswiki-extension` and `yeswiki-theme` — and mapped them in `composer.json`'s `extra.installer-paths` to `tools/{$name}` and `themes/{$name}`, so that `composer require` would drop an extension or a theme where the wiki actually looks for it.

Composer cannot do that on its own. `composer/installers` resolves install paths only for the package types in its own hardcoded framework list, which has never included YesWiki; the Composer FAQ is explicit that `installer-paths` "cannot be used to change the path of any package". The gap was bridged by `oomphinc/composer-installers-extender`, a ~50-line plugin whose whole job is to override `supports()` so that any type named in `extra.installer-types` is accepted. That package was archived on 6 May 2026 and marked abandoned with no replacement suggested. The two forks on Packagist both describe themselves as temporary.

Meanwhile the channel that actually distributes extensions and themes is the wiki's own: `AutoUpdateService` reads `repository.yeswiki.net`, and `PackageTool`/`PackageTheme` unpack zips into `tools/` and `themes/`. That is what the admin panel offers, what `UpgradeCommand` drives, and what farm-wide updates assume (ADR-0007). The Composer route was a second, parallel channel — and its entire installed base was one package: `yeswiki/theme-margot`, which is no longer the default theme. No package of type `yeswiki-extension` has ever been published.

So we decided the wiki's repository is the **only** distribution channel for extensions and themes. Core drops `oomphinc/composer-installers-extender` (and with it `composer/installers`, which was never a direct dependency), drops the `extra.installer-types` / `extra.installer-paths` block those plugins read, and empties `allow-plugins` — core now runs no Composer plugin at all. `yeswiki/theme-margot` drops its own `composer/installers` requirement to match.

## Considered Options

- **Keep the abandoned plugin** — rejected. Archived is not deleted and 2.0.1 keeps working today, but an unmaintained plugin sits in the install path of every YesWiki: the next Composer major, or a PHP version it was never tested against, breaks `composer install` for everyone. That is a standing risk carried on behalf of one theme.
- **Pin one of the Packagist forks** — rejected: both say in their own description that they are temporary and not a permanent home.
- **Get YesWiki added to `composer/installers` upstream** (one installer class, one line in its type map) — not chosen. It is the canonical answer to "how do I install to a custom path", and it would work. But it buys back a channel nothing currently uses, and leaves the project depending on someone else's release cadence for a path the admin UI already covers.
- **Ship our own `composer-plugin` package** — not chosen, same reason, plus a package to publish and maintain forever.
- **Move the directories ourselves in `post-install-cmd`** — rejected. The hook exists (`ComposerScriptsHelper`), but Composer's `installed.json` would still record those packages under `vendor/`, so updating or removing them afterwards would misbehave. Fighting the tool's bookkeeping is worse than not using the tool.

## Consequences

`composer require yeswiki/theme-margot` no longer installs a usable theme — it lands in `vendor/yeswiki/theme-margot`, where nothing looks. Anyone who installed a theme that way has to switch to the repository. This needs saying out loud in the theme's README, or the listing withdrawn from Packagist, so the old route fails loudly rather than silently producing a theme that never appears.

Extension and theme authors have one packaging target instead of two: a zip the repository serves. `"type": "yeswiki-theme"` in a package's `composer.json` becomes decoration — no installer reads it.

Core's dependency install now runs no third-party plugin code at all. `allow-plugins` is `{}` rather than absent, so a future dependency that ships a plugin is skipped with a message instead of prompting for approval — a deliberate posture, not an oversight.

If a Composer route is ever wanted again, the way back is upstream — a PR adding a YesWiki installer to `composer/installers` — not another bridge plugin. Re-adding `extra.installer-paths` alone would do nothing.

## Amendments

**The repository distributes the binary too, and that is why it gets a signing key (2026-08-21,
ADR-0023).** The self-contained binary replaces itself, so YesWiki now has a distribution channel
for **core** and not only for extensions and themes. It goes to the same host, for the reason this
ADR already gives: a second channel is a second thing every wiki trusts, and `yeswiki_repository`
exists so a private or air-gapped mirror can be that host. GitHub Actions builds the binaries and
publishes them into `repository.yeswiki.net` rather than serving them from GitHub releases.

Two things change in kind rather than degree. The artefacts are per-platform executables and
detached signatures, not the zips `PackageTool` and `PackageTheme` unpack, so the repository grows
an index shape it did not have. And because a binary that rewrites its own executable on the
strength of an HTTP response is a much larger blast radius than a theme zip, transport trust is no
longer enough: releases are signed with an ed25519 key whose public half is compiled into the
binary, verified offline, with no PKI involved. That key is a long-lived operational
responsibility the project did not have before. Losing it strands every installed binary on its
current version. Leaking it owns all of them.
