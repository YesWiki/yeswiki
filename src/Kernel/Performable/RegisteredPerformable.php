<?php

namespace YesWiki\Kernel\Performable;

/**
 * A namespaced action or handler resolved through ActionRegistry rather than found by
 * scanning a directory (wave-two ticket 06).
 *
 * The name matters: `{{entrylist}}` in a page body is *user data*, so the mechanism may
 * change but the name may not. Under the directory scan the name came from the filename,
 * which is why the classes could not have namespaces -- and why YesWikiAction derived it
 * from `get_class($this)`, something that silently breaks as soon as a class gains one.
 * Implementations state their name instead of having it inferred.
 */
interface RegisteredPerformable
{
    /**
     * The name this is invoked by: `{{name}}` for actions, `/PageName/name` for handlers.
     * Lowercase, and stable -- changing it breaks stored page content.
     */
    public static function performableName(): string;
}
