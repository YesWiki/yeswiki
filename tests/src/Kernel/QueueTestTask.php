<?php

namespace YesWiki\Test\Kernel;

use YesWiki\Kernel\Queue\ScheduledTask;

/** The smallest recurring task: a message the schedule can name, and nothing to do. */
final class QueueTestTask implements ScheduledTask
{
    public function __construct(private string $id = 'test-task', private ?string $label = null)
    {
    }

    public function taskId(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label ?? "Test task {$this->id}";
    }
}
