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

### Linters & Formatters

Please install relevant extension and enable auto formatting on your editor.

Alternatively you can run `make lint`

| Language                    | Linter/Formatter                                             |
| --------------------------- | ------------------------------------------------------------ |
| Php                         | `php-cs-fixer`                                               |
| Javascript                  | `eslint`                                                     |
| Twig                        | no automatic linter. Couldn't find one which is good enough. |
| CSS, Yaml, JSON, Markdown.. | `prettier`                                                   |

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