<?php

namespace YesWiki\Identity\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/**
 * The account this request created while saving an entry, if it created one.
 *
 * A form with a user field makes an account as a side effect of somebody filling it in. Two
 * things downstream have to know: the entry is written **as** that new user rather than as the
 * anonymous visitor who submitted it, and the activation mail must not be sent to somebody who is
 * in the middle of signing up through a form.
 *
 * It was `$GLOBALS['created_user_name']`, set during field formatting and never unset. Under
 * worker mode (ADR-0024) every later save in that process would be written as a user who has
 * nothing to do with it. `forget()` is called by the one place that acts on it, so the lifetime
 * is as long as the work and no longer.
 */
class AccountJustCreated implements RequestScopedState
{
    private ?string $name = null;

    public function record(string $name): void
    {
        $this->name = $name;
    }

    /** The account created during this request, or null when none was. */
    public function name(): ?string
    {
        return $this->name;
    }

    public function isRecorded(): bool
    {
        return $this->name !== null;
    }

    public function forget(): void
    {
        $this->name = null;
    }

    public function startNewRequest(): void
    {
        $this->name = null;
    }
}
