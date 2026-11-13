<?php

namespace YesWiki\Kernel\Message;

/** "Send this mail to these recipients": a notification built in the request and sent off it, ten recipients at most, retried when the server refuses it (ADR-0030). */
final class SendMail
{
    /** @param list<string> $recipients */
    public function __construct(
        public readonly string $sender,
        public readonly string $senderName,
        public readonly array $recipients,
        public readonly string $subject,
        public readonly string $text,
        public readonly string $html = '',
        public readonly bool $keepSender = false,
    ) {
    }
}
