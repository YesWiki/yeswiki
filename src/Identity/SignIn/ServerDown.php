<?php

namespace YesWiki\Identity\SignIn;

/** The statuses a reverse proxy answers when the server behind it is down. */
final class ServerDown
{
    public const STATUSES = [502, 503, 504];

    public static function is(int $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
