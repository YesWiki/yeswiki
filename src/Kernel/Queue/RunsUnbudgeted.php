<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message that must not run on a request's budget, such as a backup or a restore an admin starts. */
interface RunsUnbudgeted
{
    public const HELD_FOR_THE_SENDER_SECONDS = 300;
}
