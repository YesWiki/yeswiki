<?php

namespace YesWiki\Kernel\Queue;

/**
 * Implemented by the message of a recurring task, so that the schedule can name it.
 *
 * The id is what the queue keys the task's row by (QueueTaskRows); the label is what an admin
 * reads on the jobs page. Neither is the message's class, which is an implementation detail that
 * a rename should not turn into a "new" task.
 */
interface ScheduledTask
{
    /** What the queue's `task_id` column holds, as the dialects' queueDdl() declare it. */
    public const MAX_ID_LENGTH = 64;

    /** Stable across releases, at most MAX_ID_LENGTH characters: it is the task's identity in the queue's `task_id`. */
    public function taskId(): string;

    /** What the jobs page shows, already translated. */
    public function label(): string;
}
