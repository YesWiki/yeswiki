<?php

namespace YesWiki\Kernel\Performable;

use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

/**
 * Dispatched around every action and handler, so an extension can hook one without the
 * filename convention (wave-two ticket 06).
 *
 * The convention it replaces was `__X.php` / `X__.php`, discovered by scanning directories.
 * It could not survive namespacing (the name came from the filename), it ran a whole
 * performable object just to tweak an argument, and it was a second event system living
 * alongside Symfony's. **Core no longer hooks itself at all** -- those callbacks were merged
 * into the classes they wrapped -- but extensions genuinely need to reach in, and this is
 * how they do it.
 *
 * Two things a hook actually did, both supported here:
 *  - a *before* hook mostly rewrote arguments (see the helloworld sample's formatArguments)
 *  - an *after* hook appended output
 *
 * Four event names fire per phase, coarse to specific:
 *   performable.before   action.before   action.greeting.before
 *   performable.after    action.after    action.greeting.after
 * Subscribe to the specific one unless you really mean every action.
 */
class PerformableEvent extends SymfonyEvent
{
    public const BEFORE = 'before';
    public const AFTER = 'after';

    private string $type;
    private string $name;
    /** @var array<mixed> */
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

    /** @return array<mixed> */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Replace the arguments the performable will run with. Only meaningful on a `before`
     * event -- by `after` the performable has already run.
     *
     * @param array<mixed> $arguments
     */
    public function setArguments(array $arguments): void
    {
        $this->arguments = $arguments;
    }

    /**
     * Merge into the existing arguments, the common case: a hook usually adjusts one key
     * rather than restating all of them.
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
