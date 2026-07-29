<?php

namespace YesWiki\Content\Service;

use Field;
use YesWiki\Wiki;

class FieldFactory
{
    protected $wiki;

    protected $availableFields;

    /** @var array per-type cache of ['byIndex' => [int => string], 'byKey' => [string => int]] */
    protected $attributeMaps = [];

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->loadAvailableField();
    }

    private function loadAvailableField()
    {
        // Load the Field attribute class
        require_once YESWIKI_SOURCE_DIR . '/src/annotations/Field.php';

        // Core's own field types -- not discoverable through the extensions loop below
        // since core isn't itself an entry in $wiki->services->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() (populated only from
        // extension directories), mirroring how TemplateEngine registers the @core Twig
        // namespace outside its own extensions loop.
        //
        // Both arguments are strings, so neither the directory nor the namespace prefix is
        // reachable by a class-name or FQCN rewrite: wave-two ticket 05 moved the fields to
        // the Content module and this scan kept pointing at src/fields + YesWiki\Core\Field,
        // silently finding nothing. Keep the two halves in step.
        $this->scanFieldsDir(YESWIKI_SOURCE_DIR . '/src/Content/Field', 'YesWiki\\Content\\Field\\');

        foreach ($this->wiki->services->get(\YesWiki\Kernel\Service\ExtensionRegistry::class)->all() as $extensionKey => $extensionDir) {
            $extensionName = ucfirst($extensionKey);
            if ($extensionName === 'Helloworld') {
                $extensionName = 'HelloWorld';
            }
            $this->scanFieldsDir(realpath($extensionDir) . '/fields', 'YesWiki\\' . $extensionName . '\\Field\\');
        }
    }

    private function scanFieldsDir(string $fullDir, string $namespace)
    {
        if (!is_dir($fullDir)) {
            return;
        }
        $fieldsFiles = array_diff(scandir($fullDir), ['..', '.']);

        foreach ($fieldsFiles as $fieldFile) {
            preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
            if (empty($matches[1])) {
                continue;
            }
            $fieldName = $matches[1];

            $fieldClass = new \ReflectionClass($namespace . $fieldName . 'Field');

            // Read PHP 8 attributes using native reflection
            $attributes = $fieldClass->getAttributes(\Field::class);

            // If there is a Field attribute
            if (!empty($attributes)) {
                $fieldAttribute = $attributes[0]->newInstance();

                // Add all listed keywords
                foreach ($fieldAttribute->keywords as $keyword) {
                    $this->availableFields[$keyword] = $fieldClass->name;
                }

                // Also use the field name as a possible keyword
                if (!isset($this->availableFields[strtolower($fieldName)])) {
                    $this->availableFields[strtolower($fieldName)] = $fieldClass->name;
                }
            }
        }
    }

    public function create(array $values)
    {
        if (!empty($this->availableFields[$values[0]])) {
            return new $this->availableFields[$values[0]]($values, $this->wiki->services);
        }

        return false;
        // throw new \Exception('Unknown field type: ' . $values[0]);
    }

    /**
     * Positional-index => attribute-key map for a field type (e.g. for 'texte':
     * [1 => 'name', 2 => 'label', ..., 6 => 'pattern', 7 => 'sub_type']), derived by
     * reflection from the FIELD_* constants of the class handling that type -- the same
     * constants its constructor reads the positional template array through, so the map
     * is by construction in sync with what the field actually consumes. When a subclass
     * redeclares an index (TextareaField's FIELD_NUM_ROWS = 4 over BazarField's
     * FIELD_MAX_CHARS = 4), the most-derived declaration names the slot. Index 0 is
     * always the type keyword and is excluded. Unknown types get an empty map.
     */
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

        // Walk the class hierarchy base-first so more-derived FIELD_* declarations
        // override inherited ones -- first by constant NAME (EnumField's FIELD_NAME = 6
        // retires BazarField's FIELD_NAME = 1 as the meaning of 'name'), then by INDEX
        // (EmailField's own FIELD_SEE_MAIL_ACLS = 4 names slot 4, not the inherited
        // FIELD_MAX_CHARS = 4).
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
        unset($byIndex[0]); // slot 0 is the type keyword itself, serialized as 'type'
        ksort($byIndex);

        return ['byIndex' => $byIndex, 'byKey' => array_flip($byIndex)];
    }
}
