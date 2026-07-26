<?php

namespace YesWiki\Core\Service;

use Field;
use YesWiki\Wiki;

class FieldFactory
{
    protected $wiki;

    protected $availableFields;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->loadAvailableField();
    }

    private function loadAvailableField()
    {
        // Load the Field attribute class
        require_once __DIR__ . '/../annotations/Field.php';

        // Core's own field types, at src/fields/ -- not discoverable through the
        // extensions loop below since core isn't itself an entry in $wiki->extensions
        // (populated only from tools/*/ directories), mirroring how TemplateEngine
        // registers the @core Twig namespace outside its own extensions loop.
        $this->scanFieldsDir(YESWIKI_SOURCE_DIR . '/src/fields', 'YesWiki\\Core\\Field\\');

        foreach ($this->wiki->extensions as $extensionKey => $extensionDir) {
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
}
