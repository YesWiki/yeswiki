<?php

namespace YesWiki\Kernel\Service;

/**
 * The raw argument array of the most recently instantiated performable, bound by reference by Performer (historic Wiki::$parameter / GetParameter() / setParameter()).
 */
class PerformableArguments implements RequestScopedState
{
    /**
     * @var array<mixed>
     */
    protected $vars = [];

    /**
     * @param array<mixed> $vars
     */
    public function bind(array &$vars): void
    {
        $this->vars = &$vars;
    }

    public function get(string $name, mixed $default = ''): mixed
    {
        return $this->vars[$name] ?? $default;
    }

    public function set(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->vars;
    }

    /**
     * Whatever the last action was given belongs to the request it was serving.
     *
     * The arguments are bound by reference as each performable runs, so at the end of a request
     * this still points at the last one's. Under a worker the next request's first action would
     * read them until something rebound (ADR-0024), which is how `{{editbar}}` came to draw
     * itself differently on a second render of the same page.
     */
    public function startNewRequest(): void
    {
        $this->vars = [];
    }
}
