<?php

namespace YesWiki\Identity\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Identity\Message\PurgeActivationKeys;
use YesWiki\Identity\Message\PurgeBotGuard;
use YesWiki\Identity\Message\PurgeRecoveryKeys;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/**
 * The housekeeping of accounts and of the anti-bot guard: the keys that expired, the tokens and counters that are past.
 *
 * One task for each, so that one that fails shows as its own on the jobs page and does not hide
 * the others.
 */
class IdentitySchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRecoveryKeys()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeActivationKeys()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeBotGuard()),
        ];
    }
}
