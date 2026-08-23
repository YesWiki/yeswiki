<?php

namespace YesWiki\Kernel\Performable;

/** A performable answering to more than one name in page content (ticket 49). */
interface AliasesPerformable extends RegisteredPerformable
{
    /**
     * Deprecated name => the arguments that name implies.
     *
     * @return array<string, array<string, string>>
     */
    public static function performableAliases(): array;
}
