<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message whose failures are routine: the generic health check on `failed` leaves it out. */
interface ToleratedFailure
{
}
