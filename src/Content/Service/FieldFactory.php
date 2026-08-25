<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Content\Field\BazarField;
use YesWiki\Kernel\Service\ClassDirectoryScanner;

class FieldFactory
{
    protected ContainerInterface $container;

    /**
     * @var array<string, class-string<BazarField>> keyword => field class
     */
    protected array $availableFields = [];

    /**
     * @var array<string, array{byIndex: array<int, string>, byKey: array<string, int>}> per-type cache
     */
    protected array $attributeMaps = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->loadAvailableField();
    }

    private function loadAvailableField(): void
    {
        $scanner = $this->container->get(ClassDirectoryScanner::class);
        foreach ($scanner->directories('Field', 'fields') as $namespace => $dir) {
            $this->scanFieldsDir($scanner->filesIn($dir), $namespace);
        }
    }

    /**
     * @param list<string> $fieldsFiles
     */
    private function scanFieldsDir(array $fieldsFiles, string $namespace): void
    {
        foreach ($fieldsFiles as $fieldFile) {
            preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
            if (empty($matches[1])) {
                continue;
            }
            $fieldName = $matches[1];

            $className = $namespace . $fieldName . 'Field';
            if (!class_exists($className) || !is_subclass_of($className, BazarField::class)) {
                continue;
            }
            $fieldClass = new \ReflectionClass($className);

            $attributes = $fieldClass->getAttributes(Field::class);

            if (!empty($attributes)) {
                $fieldAttribute = $attributes[0]->newInstance();

                foreach ($fieldAttribute->keywords as $keyword) {
                    $this->availableFields[$keyword] = $className;
                }

                if (!isset($this->availableFields[strtolower($fieldName)])) {
                    $this->availableFields[strtolower($fieldName)] = $className;
                }
            }
        }
    }

    /**
     * @param array<int|string, mixed> $values positional field definition, keyed by the FIELD_* constants
     *
     * @return BazarField|false false when no field type answers to $values[0]
     */
    public function create(array $values): BazarField|false
    {
        $type = (string)($values[0] ?? '');
        if (empty($this->availableFields[$type])) {
            return false;
        }
        $fieldClass = $this->availableFields[$type];

        return new $fieldClass($values, $this->container);
    }

    /**
     * Positional-index => attribute-key map for a field type (e.g.
     *
     * @return array<int, string>
     */
    public function getAttributeIndexToKeyMap(string $type): array
    {
        return $this->getAttributeMap($type)['byIndex'];
    }

    /**
     * Inverse of getAttributeIndexToKeyMap(): attribute-key => positional index.
     *
     * @return array<string, int>
     */
    public function getAttributeKeyToIndexMap(string $type): array
    {
        return $this->getAttributeMap($type)['byKey'];
    }

    /**
     * @return array{byIndex: array<int, string>, byKey: array<string, int>}
     */
    private function getAttributeMap(string $type): array
    {
        if (!isset($this->attributeMaps[$type])) {
            $this->attributeMaps[$type] = $this->buildAttributeMap($type);
        }

        return $this->attributeMaps[$type];
    }

    /**
     * @return array{byIndex: array<int, string>, byKey: array<string, int>}
     */
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
