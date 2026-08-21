<?php

namespace YesWiki\Content\Service;

use Field;
use Psr\Container\ContainerInterface;
use YesWiki\Kernel\Service\ClassDirectoryScanner;

class FieldFactory
{
    protected ContainerInterface $container;

    protected $availableFields;

    /**
     * @var array per-type cache of ['byIndex' => [int => string], 'byKey' => [string => int]]
     */
    protected $attributeMaps = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->loadAvailableField();
    }

    private function loadAvailableField()
    {
        require_once YESWIKI_SOURCE_DIR . '/src/annotations/Field.php';

        $scanner = $this->container->get(ClassDirectoryScanner::class);
        foreach ($scanner->directories('Field', 'fields') as $namespace => $dir) {
            $this->scanFieldsDir($scanner->filesIn($dir), $namespace);
        }
    }

    /**
     * @param list<string> $fieldsFiles
     */
    private function scanFieldsDir(array $fieldsFiles, string $namespace)
    {
        foreach ($fieldsFiles as $fieldFile) {
            preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
            if (empty($matches[1])) {
                continue;
            }
            $fieldName = $matches[1];

            $fieldClass = new \ReflectionClass($namespace . $fieldName . 'Field');

            $attributes = $fieldClass->getAttributes(\Field::class);

            if (!empty($attributes)) {
                $fieldAttribute = $attributes[0]->newInstance();

                foreach ($fieldAttribute->keywords as $keyword) {
                    $this->availableFields[$keyword] = $fieldClass->name;
                }

                if (!isset($this->availableFields[strtolower($fieldName)])) {
                    $this->availableFields[strtolower($fieldName)] = $fieldClass->name;
                }
            }
        }
    }

    public function create(array $values)
    {
        if (!empty($this->availableFields[$values[0]])) {
            return new $this->availableFields[$values[0]]($values, $this->container);
        }

        return false;
    }

    /** Positional-index => attribute-key map for a field type (e.g. */
    public function getAttributeIndexToKeyMap(string $type): array
    {
        return $this->getAttributeMap($type)['byIndex'];
    }

    /** Inverse of getAttributeIndexToKeyMap(): attribute-key => positional index. */
    public function getAttributeKeyToIndexMap(string $type): array
    {
        return $this->getAttributeMap($type)['byKey'];
    }

    private function getAttributeMap(string $type): array
    {
        if (!isset($this->attributeMaps[$type])) {
            $this->attributeMaps[$type] = $this->buildAttributeMap($type);
        }

        return $this->attributeMaps[$type];
    }

    private function buildAttributeMap(string $type): array
    {
        if (empty($this->availableFields[$type])) {
            return ['byIndex' => [], 'byKey' => []];
        }

        $chain = [];
        for ($class = new \ReflectionClass($this->availableFields[$type]); $class; $class = $class->getParentClass()) {
            array_unshift($chain, $class);
        }

        $byName = [];
        foreach ($chain as $depth => $class) {
            foreach ($class->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                $name = $constant->getName();
                if (!str_starts_with($name, 'FIELD_') || !is_int($constant->getValue())) {
                    continue;
                }
                $byName[$name] = ['index' => $constant->getValue(), 'depth' => $depth];
            }
        }

        $byIndex = [];
        $depthAtIndex = [];
        foreach ($byName as $name => $info) {
            $index = $info['index'];
            if (!isset($byIndex[$index]) || $info['depth'] >= $depthAtIndex[$index]) {
                $byIndex[$index] = strtolower(substr($name, strlen('FIELD_')));
                $depthAtIndex[$index] = $info['depth'];
            }
        }
        unset($byIndex[0]);
        ksort($byIndex);

        return ['byIndex' => $byIndex, 'byKey' => array_flip($byIndex)];
    }
}
