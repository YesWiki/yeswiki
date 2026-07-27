# extensions/

Shared YesWiki extensions (formerly `tools/`). Everything here is loaded for
every instance of a farm sharing this source tree; instances cannot write into
this directory — extension installs from a farm satellite go to that
instance's own `custom/extensions/` instead, which also shadows a same-named
shared extension.

Only the `helloworld` sample extension is tracked in git; extensions
installed via the update system stay untracked.
