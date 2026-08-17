# Upgrading to YesWiki Ectoplasme

Ectoplasme is a **major** release. It removes legacy subsystems, renames every French
identifier a page body or a template can contain, and moves data between tables. Upgrading a
Doryphore wiki is not a file swap.

**The goal is that `migrate` does all of it.** Most of it already does — 35 migrations ship with
this release and they cover every schema and stored-content change listed below. What is left in
[Still by hand](#still-by-hand) is the residue, and each entry there says whether a migration can
absorb it and what is tracked to do so. If you find yourself doing something by hand that is not
in that list, that is a bug in this document.

---

## Before you start

**Take a backup, and check you actually have one.**

```bash
./yeswicli core:archive
```

This works on all three drivers. Two things worth knowing if you are upgrading from an earlier
Ectoplasme pre-release rather than from Doryphore:

- **PostgreSQL could not produce a database backup at all** until this release — there is no
  `SHOW CREATE TABLE`, so the table structure was simply never exported. It is rebuilt from the
  system catalogs now.
- **SQLite could produce one, but it could not be restored** once the wiki had a search index. The
  FTS5 virtual table's internal storage was dumped as though it were ordinary data, and replaying
  the archive died part-way through. That is the worse of the two failures, because it surfaced at
  restore time — the archive looked fine right up until the moment it was needed.

If you are holding an archive taken by an earlier Ectoplasme pre-release, take a fresh one after
upgrading and keep that instead. Do not skip this step: everything below is a one-way change.

### Requirements

|            | Doryphore     | Ectoplasme                                                                                          |
| ---------- | ------------- | --------------------------------------------------------------------------------------------------- |
| PHP        | 7.4+          | **8.3+** (8.3, 8.4 and 8.5 are tested in CI)                                                        |
| Database   | MySQL/MariaDB | **MySQL/MariaDB, SQLite or PostgreSQL** — all three are supported and tested                        |
| Extensions |               | `ctype curl fileinfo filter gd iconv json mbstring pcre pdo zip` + the PDO driver for your database |

---

## The upgrade

1. **Back up** (above).
2. **Replace the code.** Either `./yeswicli upgrade`, or the `{{update}}` admin screen, or
   unpack the release over your instance by hand.
3. **Regenerate the autoloader.**

   ```bash
   composer install
   ```

   Not optional, and not covered by replacing the files. PHP namespaces are mapped in
   `vendor/composer/autoload_psr4.php`, which composer writes — it is not read from
   `composer.json` at runtime. Ectoplasme splits the application into eleven namespaces
   Doryphore did not have (`YesWiki\Import\`, `YesWiki\Render\`, `YesWiki\Content\` and the
   rest), so an instance whose files were swapped without this boots with an autoloader that
   cannot see most of the code.

   If you skip it you get a container error naming whichever file sorts first in the first
   namespace it failed on — for example `Expected to find class
"YesWiki\Import\Service\ImapImporter" … but it was not found!`. **That file is fine**; it
   is a symptom, not the cause. YesWiki now detects this at boot and says so instead.

   Restart PHP-FPM afterwards if you run an opcode cache, or it will keep serving the old map.

4. **Run the migrations.**

   ```bash
   ./yeswicli migrate
   ```

   The `{{update}}` screen runs them too, so if you upgraded that way they have already run.

   > **Read the output.** `migrate` prints one line per migration and **exits 0 even when a
   > migration fails** — `MigrationService` collects per-migration errors into a message list
   > rather than aborting. A failure looks like `AU_ERROR | Migration X (date) failed with
error ...`, and the migration stays pending, so fixing the cause and re-running is safe.
   > Do not read a zero exit status as success; grep the output for `AU_ERROR`.

5. **Work through [Still by hand](#still-by-hand).**
6. **Tell your users about passwords** (see [Passwords](#passwords)) — some of them will be
   signed out and unable to sign back in until they reset.

Migrations are idempotent and re-runnable. `./yeswicli migrate` on an up-to-date wiki prints
`No migrations to run`.

---

## What migrations do for you

Nothing in this section needs your attention. It is here so you can tell whether a change you
notice was intended, and so the list of what is _not_ automated is meaningful by contrast.

**Tables.** `acls`, `nature`, `users`, `referrers` and `links` are dropped. Their contents move
into `pages` rows first where they still exist as concepts — users and uploaded files are now
Content, with their own forms. `pages` gains `metadata`, `type` and `parent` columns.

**Page bodies become JSON.** Every `pages.body`, for every revision, of every Content type.
Keywords move out of `triples` into `body.keywords`.

**Form and entry bodies are re-keyed to English.** `bn_id_nature` → `id`, `id_fiche` → `tag`,
and the rest; form templates (`bn_template`) become native JSON field objects; the five
pseudo-fields (`titre`, `acls`, `metadatas`, …) are extracted out of the template into real
form properties.

**Content sitting on a tag the router now owns is renamed off it** — reserved tags, plus
`search`, `dashboard` and `admin`. Such a page was unreachable by its own name; renaming is the
only thing that gives it a URL back. Its triples follow it.

**The search index is created and every Content queued for it.** If it ever looks stale or
incomplete, `./yeswicli search:reindex` rebuilds it.

**Layout pages become configuration.** `PageTitre`, `PageMenuHaut` and `PageRapideHaut` become
`layout_*` config keys; `PageCss` becomes `custom/styles/custom.css`; `LookWiki` is retired and
its links point at `admin/preset`.

**Your presets are rewritten, and get shorter.** A Preset used to declare 49 values; it now
declares the 31 that are _decisions_, and core computes the rest — every hover colour, the
muted text, the border shades, the focus ring, the panel and ink behind each status colour,
the shadow colours and the corner radii. The eleven spacing steps become three, and the
measures become sliders rather than lengths you type. What each of your files still said is
carried over; the top bar's and footer's colours, a colour for each of the six heading levels
and the multipliers are seeded from what your preset already implied, so nothing is left blank
and nothing is flagged incomplete. Heading _sizes_ are new — until now a heading's size was the
browser's business and not a Preset's — so they start from core's ramp.

**The `colored-navbar` theme style is gone**, because the top bar's colours are a Preset's
now (`--yw-navbar-bg`, `--yw-navbar-text`). If you were using it, the migration gives your
presets its coloured bar and puts your `favorite_style` back to the theme's default — so the
bar looks the same, and it is now a colour picker on `admin/preset` rather than a stylesheet
in a different screen.

Spacing is also **two numbers per step now** — vertical and horizontal — because text is wider
than it is tall. Your converted preset gets its old value on the vertical axis and core's ratio
on the horizontal, so it stays proportionally as tight or as roomy as you had it.

**Expect the spacing to shift.** Collapsing eleven steps to three remaps 890 rules: the
widest gaps get narrower (`6rem` → `2rem`) and the commonest one tightens slightly (`1rem` →
`0.75rem`). Headings also get explicit sizes where they previously took the browser's, so a
heading may be a little larger. All three are one slider each on `admin/preset` if the result
is not what you want.

**Per-action ACLs are re-keyed to the new action names.** This one is worth knowing about
because of what it prevents: per-action permissions live in your config under
`permissions.action.<name>`, and renaming `{{gererdroits}}` to `{{adminacls}}` would have
orphaned the entry and fallen back to `*` — everybody. A restriction silently becoming
world-readable is why this was not deferred with the rest of the rename work.

**Your config file is read as-is.** `wakka.config.php` is still found if you have not renamed it
to `yeswiki.config.php`; `$wakkaConfig` is accepted as well as `$yeswikiConfig`; `mysql_host`
and friends map to `db_host`, `wakka_name` to `yeswiki_name`; `debug: 'yes'` becomes `true`; and
`base_url` values containing `/wakka.php?wiki=` are rewritten. There is nothing to edit.

**Old template names in stored content still resolve.** `{{entrylist template="liste.tpl.html"}}`
finds `liste.twig`. The name is user data and was left alone deliberately — only the engine
behind it changed.

---

## Still by hand

### 1. Action names in files on disk

Twenty action names and forty-five parameter names became English.

**In page bodies this is migrated for you** — `RenameActionsAndParametersInBodies` rewrites every
revision of every row, resolving each call's parameters against the action name as it finds it,
and leaves parameter values and template filenames alone. It names the rewritten pages in the
administrative log. Nothing to do.

**In files, it is not.** A squelette, theme template or custom template that calls an action by
its French name — as wiki syntax or through Twig's `action('...')` helper — is yours to fix:
silently editing your theme from a database migration is not something an upgrade should do. The
migration **reports** every such file to the administrative log, so check there after upgrading,
and use `docs/action-name-renames.json` as the mapping.

The renames, for reference when fixing those files:

| old                                    | new                                   |
| -------------------------------------- | ------------------------------------- |
| `{{bazarliste}}`                       | `{{entrylist}}`                       |
| `{{bazarcarto}}`                       | `{{entrymap}}`                        |
| `{{bazartable}}`                       | `{{entrytable}}`                      |
| `{{bazarexport}}` / `{{bazarimport}}`  | `{{entryexport}}` / `{{entryimport}}` |
| `{{bazarfollow}}`                      | `{{entryfollow}}`                     |
| `{{bazaruserpage}}`                    | `{{entryuserpage}}`                   |
| `{{bazarlistecategorie}}`              | `{{entrylistcategory}}`               |
| `{{calendrier}}`                       | `{{calendar}}`                        |
| `{{abonnement}}` / `{{desabonnement}}` | `{{subscribe}}` / `{{unsubscribe}}`   |
| `{{nuagetag}}`                         | `{{tagcloud}}`                        |
| `{{valeur}}`                           | `{{value}}`                           |
| `{{gererdroits}}` / `{{gererthemes}}`  | `{{adminacls}}` / `{{adminthemes}}`   |
| `{{ariane}}`                           | `{{breadcrumb}}`                      |
| `{{doubleclic}}`                       | `{{doubleclick}}`                     |
| `{{barreredaction}}`                   | `{{editbar}}`                         |
| `{{titrepage}}`                        | `{{pagetitle}}`                       |
| `{{moteurrecherche}}`                  | `{{searchform}}`                      |

`{{bazar}}` **keeps its name.** It is the BazaR admin console rather than an entry, `bazar` is
the product's own word for that screen, and it is the most widely written action call in the
ecosystem.

Parameter **values** and template filenames are user data and unchanged, so
`{{searchform template="moteurrecherche_button.twig"}}` is correct and deliberate: the action
moved, the filename did not.

**No handler was renamed**, so no URL changed. `/MyPage/revisions`, `/MyPage/raw`,
`/MyPage/iframe` and the other 21 all still work — inbound links and bookmarks are safe. This
was the risk the rename work was most worried about, and it turned out not to exist: every
handler name was already English.

**Two parameters are not migrated anywhere, because they are URL query parameters**:
`?tri=` on the `listpages` handler (now `?sort=`) and `?utilisateur=` on `rss` (now `?user=`). A
migration cannot rewrite a link somebody has already shared or bookmarked. If you have published
such links, republish them with the new spelling.

### 2. Extensions in `tools/` are not loaded

Extensions now live in `extensions/` (shared, in the source tree) and `custom/extensions/`
(per-instance). **`tools/` is not scanned at all**, so anything still there is silently ignored —
no error, the features just stop existing.

```bash
mv tools/* custom/extensions/     # per-instance, the usual case for a single wiki
```

Check each extension is Ectoplasme-compatible before trusting it: extensions were explicitly out
of scope for the core rename work, so one written for Doryphore will refer to French action names,
dropped tables and the deleted `Wiki` class.

> **Automatable.** Moving directories is something a migration can do; it is not done because
> silently relocating third-party code is a worse default than telling you to. Tracker ticket 33
> covers whether this should warn rather than move.

### 3. Custom `.tpl.html` templates must be ported to Twig

The `tpl.html` engine is gone. Stored _names_ alias to `.twig` (above), which means your
`custom/templates/mylist.tpl.html` file is never loaded — the alias looks for `mylist.twig`.

Port each one and save it with a `.twig` extension. **No migration can do this**: it is a
translation between two template languages, not a rename.

### 4. Custom Twig templates that use core identifiers

Twig variable, macro and macro-parameter names inside `templates/` and `themes/` were renamed to
English. A custom template of yours that extends a core template, includes one, or calls a core
macro will refer to identifiers that no longer exist.

There is no rename map for this one — the work was verified by a sweep rather than recorded as a
table — so the practical approach is to diff your override against the core template it is based
on. **A migration cannot help here either**: the sandbox cannot cover overrides, and a template
override is arbitrary code against an interface that changed.

### 5. FontAwesome icon classes in your own markup

FontAwesome is no longer shipped. Icons come from a generated Tabler sprite. Core templates map
historical names through `iconFromLegacy()`, so shipped markup is fine, but
`<i class="fa fa-user"></i>` written in _your_ template or page renders nothing.

Use the `icon()` Twig helper, or `iconFromLegacy()` if you want the legacy name translated.

### 6. CSS and JavaScript targeting `bazar-list*`

Everything named `bazar-list…` is named `entry-list…` now — the list is a list of **entries**,
and `bazar` was the old name for the thing that holds them:

| was                                      | is                                       | what it is                            |
| ---------------------------------------- | ---------------------------------------- | ------------------------------------- |
| `.bazar-list`                            | `.entry-list`                            | the class on a list of entries        |
| `.bazar-list-dynamic-container`          | `.entry-list-dynamic-container`          | the mount point of a dynamic list     |
| `.bazar-list-dynamic-template-container` | `.entry-list-dynamic-template-container` | the mount point of its entry template |
| `.bazar-lists`                           | `.entry-lists`                           | a wrapper some markup uses            |
| `id="bazar-list-<n>"`                    | `id="entry-list-<n>"`                    | one list on the page, by number       |
| `bazar-list-dynamic-ready` (event)       | `entry-list-dynamic-ready`               | fired when a dynamic list has mounted |

Core's stylesheets, templates and scripts moved together, so nothing shipped is affected. What
a migration cannot reach is **yours**: a selector in `custom/styles/custom.css`, a template of
your own, or a `<style>` block written inside a page's own text — that last one is common,
since styling a list by dropping `<style>.bazar-list …</style>` above it is a documented trick.

Search your CSS, templates, page content and any custom JavaScript for `bazar-list` and rename
it. The event matters if you wrote code that waits for a dynamic list: nothing in core listens
for it, so it exists precisely for scripts of yours, and a listener on the old name will simply
never fire again.

`.entry-list` also has a bottom margin of its own now (`--yw-space-lg-y`); it had none, so
whatever followed a list ran straight into it. If you had added one yourself, you will now have
both.

### 7. Images are converted and capped

**New pictures are uploaded as WebP.** The browser converts an image and fits it inside
1920x1920 before sending it, so what the wiki stores is a few hundred kilobytes rather than a
phone's twelve megapixels. That is the same bound images are _served_ at, so a stored picture
needs no resized copy made of it -- raise `image-upload-max-width`/`-height` if your wiki is
also where the full-resolution originals are meant to live. GIFs, SVGs and anything already smaller than the cap are left alone,
and so is every image already uploaded — nothing is rewritten in place.

**A syndicated feed's images are downloaded and served from here.** `{{syndication}}` used to
put the publisher's own image URL into every card, so a page showing three feeds sent each
reader off to three other sites. The picture is now fetched once, shrunk to the same cap, and
kept in `cache/remote/`, which the wiki serves as a static file. Delete that directory whenever
you like; it refills on demand. Anything that cannot be fetched falls back to the remote
address, exactly as before.

**Pictures are served at the size the page uses.** `{{attach}}` and a `{{section}}` background
now ask the download route for a copy no larger than 1920 on its longest side (or exactly the
size the `size=`/`width=` parameters name). The copy is made on first use and cached beside the
original, behind the same permission check; the original is untouched and is what a download
gives. A picture smaller than the cap is served as it is, not enlarged to it.

Both are configurable — `image-upload-*` and `image-render-*`, listed in the admin
documentation. `image-upload-format: ''` turns the conversion off entirely.

### 8. Removed features

Check whether any page or template of yours depends on these, because nothing replaces them:

- **GoGoCarto** integration — removed.
- **Referrers** — the `referrers` table and everything reading it. There is no referrer report.
- **Backlinks / `links`** — the link graph table is gone.
- **Displaying another wiki's entries at page-view time.** `{{entrylist id="https://other.wiki|4"}}`
  fetched a remote wiki over HTTP every time the page was rendered, through a cache of its own. It
  is gone, along with the nine `external*` field types and the `baz_external_service` config block.
  A field whose linked form was a URL is likewise no longer resolved remotely.

  **The replacement is the Importer**, which already exists: it copies the remote entries into this
  wiki, so they become searchable, obey this wiki's permissions, can be edited, and stay available
  when the other site is not. Set a source up on the importers admin screen and point the list at
  the resulting local form. Credentials are optional — leave them blank to read a public remote
  form, which is what the old syntax did.

  A stored call naming another wiki now shows a sentence explaining this in place of the list; the
  rest of the page renders normally. **A migration lists every affected page in the administrative
  log**, so check there after upgrading — it cannot be automated, because choosing the replacement
  means deciding which local form to use, whether the copy may diverge, and whether files are
  downloaded or linked.

- **Nine page-handler URLs are gone.** A handler is a way of looking at a page, and these were not
  — they were output formats, state changes, a form and a cron job wearing a page's URL. Each has a
  replacement, but **the old paths return nothing, with no redirect**:

  | gone                                | use instead                                           |
  | ----------------------------------- | ----------------------------------------------------- |
  | `?PageName/rss`                     | `?api/entries/rss` (same query parameters)            |
  | `?PageName/tagrss`                  | `?api/tags/rss&tags=…`                                |
  | `?PageName/xml`                     | `?api/pages/PageName/xml`                             |
  | `?PageName/addcomment`              | `POST ?api/comments` (already where the form posted)  |
  | `?PageName/claim`                   | `POST ?api/pages/PageName/claim`, `…/comments-access` |
  | `?PageName/mail`                    | `?api/contact/form&pageTag=…`                         |
  | `?PageName/sendmail&key=…&period=…` | `./yeswicli contact:send-digest -p day`               |
  | `?PageName/filemanager`             | the `{{filemanager}}` action                          |
  | `?PageName/qrcodetroc`              | the `{{qrcodetroc}}` action                           |
  | `?PageName/listpages&tags=X`        | `?search&tags=X`                                      |

  **If you subscribe to a feed, or link to one from another site, it stops working.** A feed reader
  gives its owner no signal beyond the feed going quiet, so re-publish the new address. Links inside
  the wiki were all repointed for you.

  Two of these are worth singling out:

  - **`sendmail` was a cron job authenticated by `?key=<contact_passphrase>` in the URL**, which
    wrote your site-wide contact passphrase into web-server logs, proxy logs and browser history.
    Replace the cron line with `cd /path/to/wiki && ./yeswicli contact:send-digest -p day`. **A wiki
    with no cron needs no line at all**: the digests now go out from the wiki's own housekeeping
    pass. If you keep a real cron _and_ have mailing-list groups, set
    `contact_disable_periodic_digest` to true so both do not send.
  - **Keyword navigation moved into search.** A tag link at the bottom of a page now opens
    `?search&tags=<keyword>`, and `tags=` takes a comma-separated list that Content must carry
    _all_ of. This is a new capability, not just a relocation: keywords used to be folded into the
    index's free-text column, so a keyword could only be matched as loosely as any other word.
    **The keyword index fills as the search queue drains** — a tag link finds nothing until then,
    which `./yeswicli search:reindex` forces.
  - **`{{qrcodetroc}}` renders inside the page** rather than filling the window as the handler did.
    Give its page a minimal squelette for the old look — that is a `layout_*` setting now.

- **The `fulltextsearch` extension** is superseded and should be uninstalled. It was never part
  of core, so nothing removed it for you — but search is now a denormalised index table in the
  wiki's own database (ADR-0015), and the extension's own indexing is at best redundant with it.
  The built-in index is not a drop-in replacement for the extension's configuration.
- **The login modal** — the navbar links to `/user` instead. A bare `{{login}}` now renders the
  **account button**, not the sign-in form; ask for `login-form.twig` by name if you want the
  fields. There is deliberately no template called `default`.

---

## Passwords

**Passwords stored as md5 no longer sign anyone in.** md5 was listed as a legacy hasher, so an
md5 hash used to authenticate once and get rehashed on the way through — which meant every md5 in
a wiki's history stayed a live credential indefinitely, for as long as its owner did not sign in.

**Nothing is deleted.** The stored hash stays exactly where it is; it is what marks the account as
needing a reset, and what keeps it findable by the lost-password flow. Affected users:

- are told so at sign-in — "your password was stored in a format this version no longer accepts" —
  rather than being told their password is wrong,
- are signed out if they had a live session or a remember-me cookie, since neither path re-checks
  a password and both would otherwise let an md5 account skip the reset for weeks,
- get back in through **Lost password**, which sets a modern hash. Nothing else about the account
  changes.

If you have disabled password reset by email (`contact_disable_email_for_password`), the message
tells them to ask an administrator instead — so make sure an administrator is reachable, and note
that an admin whose _own_ password is md5 must reset it the same way.

**No migration can do this for you**, and none pretends to: rehashing needs the plain password,
which by construction nobody has. Expect reset requests in the days after you upgrade, in
proportion to how long the wiki has been running.

---

## If something goes wrong

- **`migrate` printed `AU_ERROR`** — the named migration is still pending and nothing partial was
  committed for it. Fix the cause and re-run; migrations are idempotent.
- **`Command "migrate" is not defined`** — the console registers the full command set only when
  `base_url` is set in your config. This almost always means the config file was not found or not
  written, not that the command is missing.
- **A page renders an error where an action used to be** — an un-renamed action name. See
  [section 1](#1-action-names-and-parameters-in-your-page-bodies--the-big-one).
- **An extension's features vanished silently** — it is probably still in `tools/`. See
  [section 2](#2-extensions-in-tools-are-not-loaded).
- **Search returns nothing** — `./yeswicli search:reindex`.
- **You need to go back** — restore the backup. There are no down migrations.
