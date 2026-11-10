<?php

namespace YesWiki\Kernel\Service;

/** A service that keeps what the database said, for one request or one message of a consumer, never longer: another process may have written since. */
interface CachesTheDatabase extends RequestScopedState
{
}
