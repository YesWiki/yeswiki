<?php

namespace YesWiki\Kernel\Performable;

/**
 * The two argument coercions a performable's `formatArguments()` is written in terms of.
 *
 * A trait rather than methods on `YesWikiPerformable`, because a template-data preparer (ticket 49) formats the same arguments and is not a performable: it is called by one.
 */
trait FormatsArguments
{
    /**
     * A page-syntax boolean: `no`, `non`, `false` and `0` are false, an absent or empty value is $default, anything else is true.
     *
     * @param mixed $param an argument array, or one value out of one
     */
    protected function formatBoolean(mixed $param, bool $default = true, string $index = ''): bool
    {
        if (is_array($param)) {
            if ($index != '' && isset($param[$index])) {
                $param = $param[$index];
            } else {
                return $default;
            }
        }
        if (is_bool($param)) {
            return $param;
        } elseif (in_array($param, [0, '0', 'no', 'non', 'false'], true)) {
            return false;
        } elseif (empty($param)) {
            return $default;
        }

        return true;
    }

    /**
     * A page-syntax list: comma separated, trimmed, and already an array if it came from one.
     *
     * @return array<mixed>
     */
    protected function formatArray(mixed $param): array
    {
        if (is_array($param)) {
            return $param;
        }

        return !empty($param) ? array_map('trim', explode(',', $param)) : [];
    }
}
