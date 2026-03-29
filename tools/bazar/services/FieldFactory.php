<?php

namespace YesWiki\Bazar\Service;

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

        foreach ($this->wiki->extensions as $extensionKey => $extensionDir) {
            $fullExtensionDir = realpath($extensionDir) . '/fields';
            if (is_dir($fullExtensionDir)) {
                $fieldsFiles = array_diff(scandir($fullExtensionDir), ['..', '.']);

                foreach ($fieldsFiles as $fieldFile) {
                    preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
                    if (empty($matches[1])) {
                        continue;
                    }
                    $fieldName = $matches[1];

                    $extensionName = ucfirst($extensionKey);
                    if ($extensionName === 'Helloworld') {
                        $extensionName = 'HelloWorld';
                    }

                    $fieldClass = new \ReflectionClass('YesWiki\\' . $extensionName . '\\Field\\' . $fieldName . 'Field');

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
