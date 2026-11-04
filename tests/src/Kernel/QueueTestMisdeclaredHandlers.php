<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

require_once 'tests/src/Kernel/QueueTestMessage.php';

/** Handlers the compiler pass must refuse, or read differently from the usual one: each carries the attribute and gets one thing wrong, or says the message it handles. */
#[AsMessageHandler]
final class QueueTestHandlerWithoutInvoke
{
}

#[AsMessageHandler]
final class QueueTestHandlerUntyped
{
    public function __invoke($message): void
    {
    }
}

#[AsMessageHandler]
final class QueueTestHandlerBuiltin
{
    public function __invoke(string $message): void
    {
    }
}

/** It says what it handles: its parameter need not tell. */
#[AsMessageHandler(handles: QueueTestMessage::class)]
final class QueueTestHandlerExplicit
{
    public function __invoke($message): void
    {
    }
}

/** The attribute belongs on the class. */
final class QueueTestHandlerOnAMethod
{
    #[AsMessageHandler]
    public function __invoke(QueueTestMessage $message): void
    {
    }
}
