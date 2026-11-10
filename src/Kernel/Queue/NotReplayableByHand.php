<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message the jobs page must not send again, such as a backup or a restore: the transport refuses to requeue it. */
interface NotReplayableByHand
{
}
