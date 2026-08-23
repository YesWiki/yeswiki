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

    /** Whatever the last action was given belongs to the request it was serving. */
    public function startNewRequest(): void
    {
        $this->vars = [];
    }
}
