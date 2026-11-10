<?php

namespace YesWiki\Kernel\Service;

/** Whether the operator runs this wiki on several replicas at once (ADR-0031), which `YESWIKI_REPLICATED=1` says. */
final class Replication
{
    public const ENV = 'YESWIKI_REPLICATED';

    public static function isOn(): bool
    {
        return filter_var(getenv(self::ENV), FILTER_VALIDATE_BOOLEAN);
    }
}
