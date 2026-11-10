<?php

namespace YesWiki\Identity\Scheduler;

use Symfony\Component\Scheduler\RecurringMessage;
use YesWiki\Identity\Message\PurgeActivationKeys;
use YesWiki\Identity\Message\PurgeBotGuard;
use YesWiki\Identity\Message\PurgeRecoveryKeys;
use YesWiki\Identity\Message\PurgeSessions;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/** The housekeeping of accounts, sessions and the anti-bot guard, one task each: the keys and sessions that expired, the tokens and counters that are past. */
class IdentitySchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRecoveryKeys()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeActivationKeys()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeBotGuard()),
            RecurringMessage::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeSessions()),
        ];
    }
}
