<?php

namespace YesWiki\Kernel\Queue;

/** Implemented by the message of a recurring task, so that the schedule can name it by a stable id and a label. */
interface ScheduledTask
{
    public const MAX_ID_LENGTH = 64;

    /** Stable across releases, at most MAX_ID_LENGTH characters: the `target` of the task's rows in the Journal. */
    public function taskId(): string;

    /** What the jobs page shows, already translated. */
    public function label(): string;
}
