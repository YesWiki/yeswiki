<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by a message that runs for its sender, not on a visitor's request: a backup or a restore an admin starts. */
interface RunsForItsSender
{
    public const HELD_FOR_THE_SENDER_SECONDS = 300;
}
