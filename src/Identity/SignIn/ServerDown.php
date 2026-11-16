<?php

namespace YesWiki\Identity\SignIn;

/**
 * What a reverse proxy answers for the server behind it when that server is down or overloaded: bad gateway, service
 * unavailable, gateway timeout. The address is right and nothing is to be changed in the configuration: the provider is unreachable.
 */
final class ServerDown
{
    public const STATUSES = [502, 503, 504];

    public static function is(int $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
