<?php

namespace YesWiki\Identity\Scheduler;

use YesWiki\Identity\Message\PurgeActivationKeys;
use YesWiki\Identity\Message\PurgeBotGuard;
use YesWiki\Identity\Message\PurgeRecoveryKeys;
use YesWiki\Kernel\Queue\ProvidesSchedule;
use YesWiki\Kernel\Queue\RecurringMessageTimezoned;
use YesWiki\Kernel\Scheduler\KernelSchedule;

/** The housekeeping of accounts and of the anti-bot guard, one task each: the keys that expired, the tokens and counters that are past. */
class IdentitySchedule implements ProvidesSchedule
{
    public function recurringMessages(): array
    {
        return [
            RecurringMessageTimezoned::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeRecoveryKeys()),
            RecurringMessageTimezoned::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeActivationKeys()),
            RecurringMessageTimezoned::every(KernelSchedule::HOUSEKEEPING_EVERY, new PurgeBotGuard()),
        ];
    }
}
