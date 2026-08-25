<?php

namespace YesWiki\Core;

/**
 * Freshness by hash of a fixed set of environment variables' current values, for the config overrides honored from the real environment (see EnvironmentConfiguration: private/.env changes are already covered by a ConfigFileHashResource, but injected variables can change with no file changing at all).
 */
class EnvValuesHashResource implements \Symfony\Component\Config\Resource\SelfCheckingResourceInterface
{
    /**
     * @var string[]
     */
    private array $names;
    private string $hash;

    /**
     * @param string[] $names environment variable names to watch
     */
    public function __construct(array $names)
    {
        $this->names = $names;
        $this->hash = self::valuesHash($names);
    }

    public function isFresh(int $timestamp): bool
    {
        return self::valuesHash($this->names) === $this->hash;
    }

    public function __toString(): string
    {
        return 'envvalueshash.' . md5(implode("\0", $this->names)) . '.' . $this->hash;
    }

    /**
     * @param string[] $names
     */
    private static function valuesHash(array $names): string
    {
        $values = [];
        foreach ($names as $name) {
            $values[$name] = getenv($name);
        }

        return md5(serialize($values));
    }
}
