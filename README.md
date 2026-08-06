# YesWiki

[YesWiki](https://yeswiki.net) is a Free Software under the AGPL licence, made for creating and managing your website, in a collaborative way.

[YesWiki](https://yeswiki.net) allows any web user, online, with any browser, to :

- create, delete, edit or comment on the pages of a site, with any number of editors or pages.
- manage access rights for each page (read, write, or comment) for a user or a group.
- layout a page content in a very intuitive and visual way, using formatting rules which require no technical skills.
- publish immediately any creation or modification of a page.
- analyze and manage the whole site through simple functions : site map, list of users, most recently modified or commented pages, etc.
- a set of templates to suit any site need in term of presentation
- ability for each part of a site to act as Wiki page : title, header, menus, footer etc. can be easily edited from a browser.
- a light but strong anti-spam solution.
- the possibility to embed documents in a page : pictures, mp3, videos, mind maps etc.
- a plugin manager and numerous extensions : user oriented database manager, tags, contact forms, etc.

## Installation

YesWiki can be installed in about ten minutes on a server which supports **PHP >= 7.3** and a **MySQL >= 5.6** database. Once installed, the YesWiki site is working immediately, and can be managed online from a web browser.

[More detailed install instructions in the INSTALL.md file](INSTALL.md).

## Translations

We are using [weblate](https://hosted.weblate.org/yeswiki) to translate our software!

## Developers

We recommend an installation through docker.

### Theme CSS (SCSS)

The bundled `yeswiki` theme ships its stylesheets as SCSS sources in
`themes/yeswiki/scss/`, compiled with [Dart Sass](https://sass-lang.com) (installed
as a dev dependency, no global install needed).

| Source                                    | Compiled output                            |
| ----------------------------------------- | ------------------------------------------ |
| `themes/yeswiki/scss/yeswiki.scss`        | `themes/yeswiki/styles/yeswiki.css`        |
| `themes/yeswiki/scss/colored-navbar.scss` | `themes/yeswiki/styles/colored-navbar.css` |

Partials (files prefixed with `_`, e.g. `_topnav.scss`) are not compiled on their
own — they are `@use`d by the two entry points above. `colored-navbar.scss` itself
`@use`s `yeswiki`, so it is a complete, standalone stylesheet (the coloured-navbar
variant), not an add-on to load on top of `yeswiki.css`.

```bash
yarn install        # installs sass, and builds the theme once (postinstall)
yarn build-theme    # one-off build
yarn watch-theme    # rebuild on every save while you work
```

Both commands write compressed CSS without source maps, so what you get locally is
byte-identical to what `yarn install` produces. **The compiled CSS is committed** —
run `yarn build-theme` and commit the resulting `.css` files along with your `.scss`
changes. They are excluded from `prettier` (see `.prettierignore`), so `make lint`
will not reformat them.

If you need readable output while debugging, add the flags on the fly:

```bash
yarn sass --watch --style=expanded themes/yeswiki/scss:themes/yeswiki/styles
```

…but rebuild with `yarn build-theme` before committing.

### Linters & Formatters

Please install relevant extension and enable auto formatting on your editor.

Alternatively you can run `make lint`

| Language                    | Formatting     | Rules                                        |
| --------------------------- | -------------- | -------------------------------------------- |
| Php                         | `php-cs-fixer` | `phpstan`                                    |
| Javascript                  | `prettier`     | `eslint`                                     |
| CSS, Yaml, JSON, Markdown.. | `prettier`     | —                                            |
| Twig                        | —              | no automatic linter good enough to adopt yet |

**One tool per question.** Prettier decides how a file is laid out; eslint only judges
whether the code is right. Javascript used to be eslint's on both counts, through
`eslint-config-airbnb-base` — unreleased since 2021, and contradicting Prettier on every
line-break question, so writing a file meant satisfying two formatters at once.
`eslint-config-prettier` now sits last in `eslint.config.mjs` and switches off anything
stylistic that creeps back in.

`make lint` checks both and writes nothing; `make fix` applies them.

If you use VS Codium, get YesWiki linting settings with `cp .vscode/settings.example.json .vscode/settings.json`

## History

YesWiki grew out of a French language version of [WakkaWiki](https://en.wikipedia.org/wiki/WakkaWiki) called [Wikini](http://wikini.net), and hence has strong French language support.

## Authors and contributors

### Initial WakkaWiki author

- 2002, 2003 Hendrik Mans <hendrik@mans.de>

### Wikini authors

- 2003 Carlo ZOTTMANN
- 2002, 2003, 2004 David DELON
- 2002, 2003, 2004 Charles NEPOTE
- 2002, 2003, 2004 Patrick PAUL
- 2003 Eric DELORD
- 2003, 2004 Eric FELDSTEIN
- 2003 Jean-Pascal MILCENT
- 2003 Jéréme DESQUILBET
- 2003 Erus UMBRAE
- 2004 David VANTYGHEM
- 2004 Jean Christophe ANDRE
- 2005 Didier Loiseau

### YesWiki authors

See <https://github.com/YesWiki/yeswiki/graphs/contributors>
