<?php

namespace YesWiki\Identity\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** The account this request created while saving an entry, if it created one. */
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
