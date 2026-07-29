<?php

namespace YesWiki\Kernel\Service;

/**
 * The raw argument array of the most recently instantiated performable, bound by
 * reference by Performer (historic Wiki::$parameter / GetParameter() / setParameter()).
 *
 * This is deliberately request-global, quirk included: Attach, TemplateHelperService
 * and a few handlers read "the current action's parameters" without being the running
 * performable, and a nested action leaves ITS arguments bound after it returns --
 * exactly what the by-reference rebinding on Wiki did.
 */
class PerformableArguments
{
    /** @var array<mixed> */
    protected $vars = [];

    /** @param array<mixed> $vars */
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

    /** @return array<mixed> */
    public function all(): array
    {
        return $this->vars;
    }
}
