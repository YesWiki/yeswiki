<?php

namespace YesWiki\HelloWorld\Message;

use YesWiki\Kernel\Queue\ScheduledTask;

/** The recurring task of the sample extension: it greets in the Journal (HelloworldSchedule). */
final class Greet implements ScheduledTask
{
    public function taskId(): string
    {
        return 'helloworld-greet';
    }

    public function label(): string
    {
        return _t('HELLOWORLD_GREET_TASK');
    }
}
