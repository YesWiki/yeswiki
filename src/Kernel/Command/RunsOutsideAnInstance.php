<?php

namespace YesWiki\Kernel\Command;

/**
 * A command that runs in a bare source tree, with no wiki behind it.
 *
 * `yeswicli` used to refuse to start at all without a `yeswiki.config.php`, which is
 * exactly backwards for the arrangement it exists to serve: one YesWiki source shared by
 * every wiki on the server. In that folder there IS no config -- it is nobody's wiki --
 * and the one thing you want to do there is make a wiki.
 *
 * So the console now boots either way and asks each command which world it needs. Almost
 * all of them need a wiki: they read its database, its pages, its extensions. The few that
 * do not say so by implementing this, and in exchange must accept a **null container** --
 * there is no container to give them.
 *
 * Implementing this is a promise about the whole command: `execute()` may not reach for a
 * service, however indirectly. A command that needs one thing from the wiki does not
 * belong here; it belongs in an instance.
 */
interface RunsOutsideAnInstance
{
}
