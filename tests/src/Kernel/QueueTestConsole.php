<?php

namespace YesWiki\Test\Kernel;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Process\Process;
use YesWiki\Kernel\Service\ConsoleService;

/** A console that starts nothing: it notes the commands it is asked to start, and answers what the test says the server would. */
final class QueueTestConsole extends ConsoleService
{
    /** @var list<string> */
    public array $commands = [];

    /** @var list<list<string|int>> the arguments each of them was started with, in the same order */
    public array $arguments = [];

    /** @var list<bool> whether each of them was started detached (startDetachedConsole()), in the same order */
    public array $detached = [];

    /** @var (\Closure(): ?Process)|null what starting a process gives; null for a process (never actually run), as on a server that can */
    public ?\Closure $answer = null;

    public function __construct()
    {
        parent::__construct(new ParameterBag([]));
    }

    public function startConsoleAsync(string $command, array $args = [], string $subfolder = '', bool $newConsole = true, int $timeoutInSec = 60): ?Process
    {
        $this->commands[] = $command;
        $this->arguments[] = $args;
        $this->detached[] = false;

        return $this->answer === null ? new Process(['true']) : ($this->answer)();
    }

    public function startDetachedConsole(string $command, array $args = []): ?Process
    {
        $this->commands[] = $command;
        $this->arguments[] = $args;
        $this->detached[] = true;

        return $this->answer === null ? new Process(['true']) : ($this->answer)();
    }
}
