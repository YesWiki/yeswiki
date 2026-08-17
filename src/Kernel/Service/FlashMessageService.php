<?php

namespace YesWiki\Kernel\Service;

/** The historic one-slot `$_SESSION['message']` flash channel (Wiki::SetMessage()/ GetMessage()). */
class FlashMessageService
{
    public function setMessage(string $message): void
    {
        $_SESSION['message'] = $message;
    }

    /** Return the pending message ('' when none) and clear the slot. */
    public function getMessage(): string
    {
        $message = $_SESSION['message'] ?? '';
        $_SESSION['message'] = '';

        return is_string($message) ? $message : '';
    }
}
