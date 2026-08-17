<?php

namespace YesWiki\Kernel\Performable;

use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

/**
 * Dispatched around every action and handler, so an extension can hook one without the filename convention (wave-two ticket 06).
 */
class PerformableEvent extends SymfonyEvent
{
    public const BEFORE = 'before';
    public const AFTER = 'after';

    private string $type;
    private string $name;
    /**
     * @var array<mixed>
     */
    private array $arguments;
    private string $output = '';

    /**
     * @param array<mixed> $arguments
     */
    public function __construct(string $type, string $name, array $arguments)
    {
        $this->type = $type;
        $this->name = $name;
        $this->arguments = $arguments;
    }

    /** 'action' or 'handler'. */
    public function getType(): string
    {
        return $this->type;
    }

    /** The invoked name: `greeting` for `{{greeting}}`, `edit` for `/PageName/edit`. */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Replace the arguments the performable will run with.
     *
     * @param array<mixed> $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * Merge into the existing arguments, the common case: a hook usually adjusts one key rather than restating all of them.
     *
     * @param array<mixed> $arguments
     */
    public function mergeArguments(array $arguments): void
    {
        $this->arguments = array_merge($this->arguments, $arguments);
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    /** Contribute markup: prepended on `before`, appended on `after`. */
    public function appendOutput(string $output): void
    {
        $this->output .= $output;
    }

    /**
     * The event names this dispatch should fire, coarse to specific.
     *
     * @return list<string>
     */
    public function eventNames(string $phase): array
    {
        return [
            "performable.{$phase}",
            "{$this->type}.{$phase}",
            "{$this->type}.{$this->name}.{$phase}",
        ];
    }
}
