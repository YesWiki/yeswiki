# extensions/

Shared YesWiki extensions (formerly `tools/`). Everything here is loaded for
every instance of a farm sharing this source tree; instances cannot write into
this directory — extension installs from a farm satellite go to that
instance's own `custom/extensions/` instead, which also shadows a same-named
shared extension.

Only the `helloworld` sample extension is tracked in git; extensions
installed via the update system stay untracked.

A folder here is an extension, but it runs on a wiki only once that wiki names it in
`active_extensions` (`./yeswicli extension:enable <name>`, or the switch on `/admin/updates`).
An extension describes itself in a `composer.json` and states no version of its own: the tag
it is published from is its version (ADR-0029). `helloworld` shows the whole shape.

