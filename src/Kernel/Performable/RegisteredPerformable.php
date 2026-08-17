<?php

namespace YesWiki\Kernel\Performable;

/**
 * A namespaced action or handler resolved through ActionRegistry rather than found by scanning a directory (wave-two ticket 06).
 */
interface RegisteredPerformable
{
    /** The name this is invoked by: `{{name}}` for actions, `/PageName/name` for handlers. */
    public static function performableName(): string;
}
