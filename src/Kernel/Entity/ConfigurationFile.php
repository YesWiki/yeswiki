<?php

namespace YesWiki\Kernel\Entity;

use YesWiki\Kernel\Service\ConfigurationService;

/**
 * @implements \ArrayAccess<array-key, mixed>
 * @implements \Iterator<int|string|null, mixed>
 */
class ConfigurationFile implements \ArrayAccess, \Iterator, \Countable
{
    private string $_file = '';
    /** @var array<array-key, mixed> */
    protected array $_parameters = [];
    protected ?ConfigurationService $configurationService = null;

    /** @param string $file path of the configuration file */
    public function __construct(string $file, ?ConfigurationService $configurationService = null)
    {
        $this->_file = $file;
        $this->_parameters = [];
        // The service is passed in, always: ConfigurationService builds these itself, and
        // PackageCore is handed one by the collection that builds it. The `$GLOBALS` fallback
        // this replaces existed for PackageCore alone (ticket 45).
        $this->configurationService = $configurationService;
    }

    public function __get(string $name): mixed
    {
        if ($name == '_file') {
            return $this->_file;
        } elseif ($name == '_parameters') {
            return $this->_parameters;
        }
        if (isset($this->_parameters[$name])) {
            return $this->_parameters[$name];
        }
        throw new \Exception("Paramètre inconnu Configuration::$name", 1);
    }

    public function __isset(string $name): bool
    {
        return isset($this->_parameters[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name != '_file') {
            $this->_parameters[$name] = $value;
        }
    }

    public function __unset(string $name): void
    {
        unset($this->_parameters[$name]);
    }

    /** @param string $arrayName name of the array the file assigns its values to */
    public function load(string $arrayName = 'yeswikiConfig'): void
    {
        if (!is_file($this->_file)) {
            return;
        }

        $rawContent = file_get_contents($this->_file);
        if ($rawContent === false) {
            return;
        }

        $yeswikiConfig = [];
        $content = str_replace([$arrayName, 'wakkaConfig', '<?php', '?>'], ['yeswikiConfig', 'yeswikiConfig', '', ''], $rawContent);
        eval($content);
        if (!empty($yeswikiConfig)) {
            $this->_parameters = $yeswikiConfig;
        }
    }

    /**
     * écrit le fichier de configuration.
     *
     * @param string|null $file
     * @param string      $arrayName
     *
     * @return bool
     */
    public function write($file = null, $arrayName = 'yeswikiConfig')
    {
        if ($this->configurationService === null) {
            throw new \Exception('this ConfigurationFile was built without a ConfigurationService, so it cannot write itself');
        }

        return $this->configurationService->write($this, $file, $arrayName);
    }

    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->_parameters[] = $value;

            return;
        }
        $this->_parameters[$offset] = $value;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->_parameters[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->_parameters[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return isset($this->_parameters[$offset]) ? $this->_parameters[$offset] : null;
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        reset($this->_parameters);
    }

    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->_parameters);
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->_parameters);
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return isset($this->_parameters[$this->key()]);
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        next($this->_parameters);
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->_parameters);
    }
}
