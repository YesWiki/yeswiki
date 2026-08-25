#!/bin/bash

# What `composer test` runs.
#
# This used to run phpunit over tests/ and then loop `ls -d tools/*/tests`, one phpunit
# invocation per bundled tool. The ectoplasme rewrite folded every tool into src/<Module>/ and
# deleted tools/ entirely, so the loop matched nothing: `ls` wrote "No such file or directory"
# to stderr, the body never ran, and the subshell still exited 0. It has been passing by
# running nothing extra ever since -- visible only as a stray error line in the CI log.
#
# Extensions keep their own tests and are not covered here: extensions/ is gitignored except
# for the bundled sample, so there is nothing for CI to find (the same reasoning phpstan.neon
# gives for only analysing extensions/helloworld).

set -e

# memory_limit is raised here rather than left to php.ini: the suite peaks around 180 MB, so a
# stock 128 MB php.ini kills it with a fatal a few percent in. That is worse than a failed run --
# the fatal also takes down YesWikiTestCase's shutdown sweep, so every fixture account and group
# created up to that point stays behind in the developer's own wiki.
exec php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests "$@"
