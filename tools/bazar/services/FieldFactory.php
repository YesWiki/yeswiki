<?php

namespace YesWiki\Bazar\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\PhpFileCache;
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
        AnnotationRegistry::registerFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'annotations' . DIRECTORY_SEPARATOR . 'Field.php');

        $reader = new CachedReader(
            new AnnotationReader(),
            new PhpFileCache(dirname(__DIR__,3) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'fields'),
            $debug = true
        );

        foreach ($this->wiki->extensions as $extensionKey => $extensionDir) {
            $fullExtensionDir = realpath($extensionDir) . DIRECTORY_SEPARATOR . 'fields';
            if (is_dir($fullExtensionDir)) {
                $fieldsFiles = array_diff(scandir($fullExtensionDir), ['..', '.']);

                foreach ($fieldsFiles as $fieldFile) {
                    preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
                    $fieldName = $matches[1];

                    $extensionName = ucfirst($extensionKey);
                    if ($extensionName === 'Helloworld') {
                        $extensionName = 'HelloWorld';
                    }

                    // TODO cache reflection class as this is a costly operation
                    $fieldClass = new \ReflectionClass('YesWiki\\' . $extensionName . '\\Field\\' . $fieldName . 'Field');

                    $annotation = $reader->getClassAnnotation($fieldClass, 'Field');

                    // If there is a Field annotation
                    if ($annotation) {
                        // Add all listed keywords
                        foreach ($annotation->keywords as $keyword) {
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
        } else {
            return false;
            // throw new \Exception('Unknown field type: ' . $values[0]);
        }
    }
}
