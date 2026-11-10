<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message whose failures are routine: once it has used its retries it is not kept in `failed`: its failure stays in the history and the Journal. */
interface ToleratedFailure
{
}
