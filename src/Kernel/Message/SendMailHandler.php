<?php

namespace YesWiki\Kernel\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use YesWiki\Kernel\Service\Mailer;

/** Hands a queued mail to the mail server, and throws what the server said when it refuses, so the queue tries again. */
#[AsMessageHandler]
final class SendMailHandler
{
    public function __construct(private readonly Mailer $mailer)
    {
    }

    public function __invoke(SendMail $message): void
    {
        $recipients = count($message->recipients) === 1 ? $message->recipients[0] : $message->recipients;
        if (!$this->mailer->send($message->sender, $message->senderName, $recipients, $message->subject, $message->text, $message->html, $message->keepSender)) {
            throw new \RuntimeException('The mail "' . $message->subject . '" was not sent: ' . $this->mailer->lastError());
        }
    }
}
