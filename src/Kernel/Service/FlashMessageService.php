<?php

namespace YesWiki\Kernel\Service;

/** The historic one-slot notice (Wiki::SetMessage()/GetMessage()), now the toast of FlashMessages, so it rides the same cookie. */
class FlashMessageService
{
    public function __construct(private readonly FlashMessages $messages)
    {
    }

    public function setMessage(string $message): void
    {
        $this->messages->toast($message);
    }

    /** Return the pending message ('' when none) and clear the slot. */
    public function getMessage(): string
    {
        return $this->messages->takeToast();
    }
}
