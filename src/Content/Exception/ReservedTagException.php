<?php

namespace YesWiki\Content\Exception;

/**
 * Thrown when Content is written on a tag the router owns (ticket 20).
 *
 * This is a backstop, not the user-facing path: every creation helper resolves its tag
 * through PageManager::suggestFreeTag(), which skips reserved tags the same way it skips
 * taken ones, and the paths where a human types a tag check first so they can say
 * *reserved* rather than *taken*. If this ever surfaces, a caller invented a tag without
 * asking -- which is the bug, not the exception.
 */
class ReservedTagException extends \Exception
{
}
