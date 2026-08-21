<?php

namespace YesWiki\Kernel\Performable;

/**
 * A performable answering to more than one name in page content (ticket 49).
 *
 * There is one `{{entrylist}}`, and `{{entrymap}}` is a spelling of it that stored pages still
 * use. An alias is not a second action: it resolves to this class before anything else
 * happens, so it is checked against **this** performable's ACL and parsed by its arguments.
 * What an alias may carry of its own is defaults, which is how `{{entrymap}}` still means
 * `{{entrylist template="map"}}` without a second class existing to say so.
 *
 * Aliases are deprecated by construction: nothing offers one, and what the palette writes is
 * always the canonical name.
 */
interface AliasesPerformable extends RegisteredPerformable
{
    /**
     * Deprecated name => the arguments that name implies.
     *
     * Defaults, not overrides: `{{entrymap template="gogomap"}}` is a webmaster being
     * specific, and the alias must not talk over them.
     *
     * @return array<string, array<string, string>>
     */
    public static function performableAliases(): array;
}
