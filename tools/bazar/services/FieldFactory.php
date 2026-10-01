<?php

namespace YesWiki\Bazar\Service;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\PhpFileCache;
use YesWiki\Wiki;

class FieldFactory
{
    private const CACHE_PATH = '/../../../cache/';
    protected $wiki;

    protected $availableFields;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
        $this->checkCacheFolderExistence();
        $this->loadAvailableField();
    }

    private function checkCacheFolderExistence()
    {
        try {
            if (!file_exists(__DIR__ . self::CACHE_PATH) || !is_dir(__DIR__ . self::CACHE_PATH)) {
                throw new \Exception('ERROR ! : Folder `cache/` not existing in the root folder on the website host ! Can you create it ? ');
            }

            if (!is_writable(__DIR__ . self::CACHE_PATH)) {
                throw new \Exception('ERROR ! : Folder `cache/` is not writable ! Can you give it write acces by ftp for example (code 770) ?');
            }
        } catch (\Exception $th) {
            echo "<div style=\"border:1px red solid;background-color: #FFCCCC;margin:3px;padding:5px;border-radius:5px;\">{$th->getMessage()}</div>";
            exit;
        }
    }

    private function loadAvailableField()
    {
        AnnotationRegistry::registerFile(__DIR__ . '/../annotations/Field.php');

        $reader = new CachedReader(
            new AnnotationReader(),
            new PhpFileCache(__DIR__ . self::CACHE_PATH . 'fields'),
            $debug = true
        );

        foreach ($this->wiki->extensions as $extensionKey => $extensionDir) {
            $fullExtensionDir = realpath($extensionDir) . '/fields';
            if (is_dir($fullExtensionDir)) {
                $fieldsFiles = array_diff(scandir($fullExtensionDir), ['..', '.']);

                foreach ($fieldsFiles as $fieldFile) {
                    preg_match("/^([a-zA-Z0-9_-]+)Field\.php$/", $fieldFile, $matches);
                    $fieldName = $matches[1];

                    $extensionName = ucfirst($extensionKey);
                    if ($extensionName === 'Helloworld') {
                        $extensionName = 'HelloWorld';
                    }

                    $fieldClass = new \ReflectionClass('YesWiki\\' . $extensionName . '\\Field\\' . $fieldName . 'Field');

                    $keywords = $this->declaredKeywords($fieldClass, $reader);

                    if ($keywords !== null) {
                        foreach ($keywords as $keyword) {
                            $this->availableFields[$keyword] = $fieldClass->name;
                        }

                        if (!isset($this->availableFields[strtolower($fieldName)])) {
                            $this->availableFields[strtolower($fieldName)] = $fieldClass->name;
                        }
                    }
                }
            }
        }
    }

    /**
     * Keywords of a field class, from its #[Field] attribute or its @Field docblock annotation.
     */
    private function declaredKeywords(\ReflectionClass $fieldClass, CachedReader $reader): ?array
    {
        foreach ($fieldClass->getAttributes(\Field::class) as $attribute) {
            $arguments = $attribute->getArguments();

            return (array)($arguments['keywords'] ?? $arguments[0] ?? []);
        }
        $annotation = $reader->getClassAnnotation($fieldClass, 'Field');

        return $annotation ? (array)$annotation->keywords : null;
    }

    public function create(array $values)
    {
        if (!empty($this->availableFields[$values[0]])) {
            return new $this->availableFields[$values[0]]($values, $this->wiki->services);
        }

        return false;
    }
}
