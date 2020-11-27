<?php

namespace YesWiki\Core\Service;

use YesWiki\Wiki;

/**
 * Loads and run Handlers, Formatters and Actions
 * Any of these object can be easily customize with before and after callback
 * To create a before callback, use same file name prefixed by "__"
 * To create an after callback, use same file name suffixed by "__"
 * For example:
 *  1) tools/bazar/actions/BazarShowAction.php
 *  2) tools/attach/actions/__BazarShowAction.php
 *  3) tools/security/actions/BazarShowAction__.php
 * When we run the action 'bazarshow', all 3 files will be executed in following order : 2,1,3
 */
class Performer
{
    const TYPES = [
        'action' => 'action',
        'handler' => 'handler',
        'formatter' => 'formatter'
    ];
    const PATHS = [
        Performer::TYPES['action'] => ['actions/'],
        Performer::TYPES['handler'] => ['handlers/', 'handlers/page/'],
        Performer::TYPES['formatter'] => ['formatters/']
    ];
    protected $wiki;
    protected $objectList; // list of all existing object

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;

        // get the list of all existing objects (actions, handlers, formatters...)
        $folders = array_merge([''], $wiki->extensions); // root folder + extensions folders
        foreach (Performer::TYPES as $type) {
            $this->objectList[$type] = [];
            foreach ($folders as $folder) {
                foreach (Performer::PATHS[$type] as $path) {
                    $this->findObjectInPath($folder . $path, $type);
                }
            }
        }
    }

    /**
     * Read existing PHP files in the current $dir, and store them inside $this->objectList
     */
    private function findObjectInPath($dir, $objectType)
    {
        if (file_exists($dir) && $dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match("/^([a-zA-Z0-9_-]+)(\.class)?\.php$/", $file, $matches)) {
                    $baseName = $matches[1]; // __GreetingAction
                    $objectName = strtolower($matches[1]); // __greetingaction
                    $objectName = preg_replace("/^__/", '', $objectName); // greetingaction
                    $objectName = preg_replace("/__$/", '', $objectName);
                    $isDefinedAsClass = false;
                    if (endsWith($baseName, ucfirst($objectType)) || endsWith($baseName, ucfirst($objectType)."__")) {
                        $objectName = preg_replace("/{$objectType}$/", '', $objectName); // greeting
                        $isDefinedAsClass = true;
                    }
                    $filePath = $dir . $file;
                    $object = &$this->objectList[$objectType][$objectName];
                    if (startsWith($file, '__')) {
                        $object['before_callbacks'][] = $filePath;
                    } elseif (endsWith($file, '__.php')) {
                        $object['after_callbacks'][] = $filePath;
                    } else {
                        $object = [
                            'filePath' => $filePath,
                            'baseName' => $baseName,
                            'isDefinedAsClass' => $isDefinedAsClass,
                            'before_callbacks' => $object['before_callbacks'] ?? [],
                            'after_callbacks' => $object['after_callbacks'] ?? [],
                        ];
                    }
                }
            }
        }
    }

    public function run($objectName, $objectType, $vars = [])
    {
        if (!Performer::TYPES[$objectType]) {
            return "Invalid type $objectType";
        }
        $objectName = strtolower($objectName);
        
        if (!$this->wiki->CheckModuleACL($objectName, $objectType)) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('ERROR_NO_ACCESS') . '</div>' . "\n";
        }
        
        // Find object
        $object = isset($this->objectList[$objectType][$objectName]) ? $this->objectList[$objectType][$objectName] : false;
        if (!$object) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('NOT_FOUND') . '</div>' . "\n";
        }
        
        // Execute main file with callbacks
        if ($object['isDefinedAsClass']) {
            require_once($object['filePath']);
            if (class_exists($object['baseName'])) {
                $objectInstance = new $object['baseName']($this->wiki);
                return $objectInstance->runWithCallbacks($vars, $object['before_callbacks'], $object['after_callbacks']);
            } else {
                die("There were a problem while loading {$object['baseName']} at {$object['filePath']}. Ensures the class exists");
            }
        } else {
            $files = array_merge($object['before_callbacks'], [$object['filePath']], $object['after_callbacks']);
            // Need to run them from YesWiki Class so the variable $this (used in all the plain PHP object) refers to YesWiki, not to Performer service
            return $this->wiki->runFilesInBuffer($files, $vars);
        }
    }

    /**
     * Retrieves the list of existing objects
     *
     * @return array An unordered array of all the available objects.
     */
    public function list($objectType)
    {
        if (!Performer::TYPES[$objectType]) {
            die("Invalid type $objectType");
        }
        return array_unique(array_keys($this->objectList[$objectType]));
    }
}
