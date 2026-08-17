<?php

namespace YesWiki\Kernel\Service;

/**
 * The mutable runtime configuration array (historic Wiki::$config / GetConfigValue()).
 *
 * @implements \ArrayAccess<string, mixed>
 */
class RuntimeConfig implements \ArrayAccess
{
    /**
     * @var array<mixed>
     */
    protected $values = [];

    /**
     * @param array<mixed> $values
     */
    public function bind(array &$values): void
    {
        $this->values = &$values;
    }

    /**
     * Historic Wiki::GetConfigValue(): scalars come back trimmed, a missing or null key yields $default when truthy, '' otherwise.
     */
    public function getValue(string $name, mixed $default = null): mixed
    {
        return isset($this->values[$name])
            ? (is_array($this->values[$name]) ? $this->values[$name] : trim((string)$this->values[$name]))
            : ($default != null ? $default : '');
    }

    /**
     * @return array<mixed>
     */
    public function all(): array
    {
        return $this->values;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    /**
     * By reference so nested element writes (`$config['a']['b'] = ...`) reach the shared storage instead of a temporary copy.
     */
    public function &offsetGet(mixed $offset): mixed
    {
        if (!isset($this->values[$offset])) {
            $null = null;

            return $null;
        }

        return $this->values[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->values[] = $value;

            return;
        }
        $this->values[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->values[$offset]);
    }
}
