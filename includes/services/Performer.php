<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use YesWiki\Core\Exception\ExitException;
use YesWiki\Core\Exception\PerformerException;
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
 * When we run the action 'bazarshow', all 3 files will be executed in following order : 2,1,3.
 */
class Performer
{
    public const TYPES = [
        'action' => 'action',
        'handler' => 'handler',
        'formatter' => 'formatter',
    ];
    public const PATHS = [
        Performer::TYPES['action'] => ['actions/'],
        Performer::TYPES['handler'] => ['handlers/', 'handlers/page/'],
        Performer::TYPES['formatter'] => ['formatters/'],
    ];
    protected $wiki;
    protected $params;
    protected $twig;

    // list of all existing object
    protected $objectList;

    public function __construct(Wiki $wiki, ParameterBagInterface $params, TemplateEngine $twig)
    {
        $this->wiki = $wiki;
        $this->params = $params;
        $this->twig = $twig;

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
     * Read existing PHP files in the current $dir, and store them inside $this->objectList.
     */
    private function findObjectInPath($dir, $objectType)
    {
        if (file_exists($dir) && $dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match("/^([a-zA-Z0-9_-]+)(\.class)?\.php$/", $file, $matches)) {
                    $baseName = $matches[1]; // __GreetingAction
                    $objectName = strtolower($matches[1]); // __greetingaction
                    $objectName = preg_replace('/^__|__$/', '', $objectName); // greetingaction
                    $isDefinedAsClass = false;
                    if (endsWith($baseName, ucfirst($objectType)) || endsWith($baseName, ucfirst($objectType) . '__')) {
                        $objectName = preg_replace("/{$objectType}$/", '', $objectName); // greeting
                        $isDefinedAsClass = true;
                    }
                    $filePath = $dir . $file;
                    $object = &$this->objectList[$objectType][$objectName];
                    if (startsWith($file, '__')) {
                        if (!isset($object['before_callbacks'])) {
                            $object['before_callbacks'] = [];
                        }
                        array_unshift($object['before_callbacks'], [
                            'filePath' => $filePath,
                            'baseName' => $baseName,
                            'isDefinedAsClass' => $isDefinedAsClass,
                        ]);
                    } elseif (endsWith($file, '__.php')) {
                        if (!isset($object['after_callbacks'])) {
                            $object['after_callbacks'] = [];
                        }
                        array_unshift($object['after_callbacks'], [
                            'filePath' => $filePath,
                            'baseName' => $baseName,
                            'isDefinedAsClass' => $isDefinedAsClass,
                        ]);
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

    /**
     * Create the performable instance described by $object and with the variables used as an execution context.
     *
     * @param array $object the object description
     * @param array $vars   the variables defined in the execution context of the object
     * @param $output the current generated output
     *
     * @return mixed the performable instance
     */
    public function createPerformable(array $object, array &$vars, &$output)
    {
        require_once $object['filePath'];
        $className = $object['baseName'];
        /* extract extension name from path to allow namespace */
        if (preg_match('/(?:tools[\\\\\\/]([A-Za-z0-9_\\-]+)|(custom))[\\\\\/][a-zA-Z0-9_\\\\\/\\-]+.php$/', $object['filePath'], $matches)) {
            $extensionName = empty($matches[1]) ? $matches[2] : $matches[1];
            $classNameWithNamespace = 'YesWiki\\' . StringUtilService::folderToNamespace($extensionName) . '\\' . $object['baseName'];
            if (class_exists($classNameWithNamespace)) {
                $className = $classNameWithNamespace;
            }
        }

        if (class_exists($className)) {
            $instance = new $className();
            $instance->setWiki($this->wiki);
            $instance->setParams($this->params);
            $instance->setArguments($vars);
            $instance->setOutput($output);
            $instance->setTwig($this->wiki->services->get(TemplateEngine::class));

            // we must save the arguments in the YesWiki object, as YesWiki::getParameter is used in many places
            // TODO once bazar will be completly rewritten, we should remove this by passing the arguments to the renderers
            $this->wiki->parameter = &$vars;

            return $instance;
        } else {
            throw new PerformerException("There were a problem while loading {$className} at " . "{$object['filePath']}. Ensures the class exists");
        }
    }

    /**
     * Run an handler, formatter or actions and all its callback.
     *
     * @param $objectName the object name
     * @param $objectType the type, corresponds to a Performer::TYPES key
     * @param array $vars the variables defined in the execution context of the object. It's an array containing the
     *                    value of each parameter given to the performable, where the names of the parameters are the key, corresponding to
     *                    the given string value. Per example, by execute the action {{include page="PageTag"}}, this array is initialized
     *                    with the page "parameter". Then, each execution change the execution context variables for the next one.
     *
     * @return string the generated output
     */
    public function run($objectName, $objectType, array $vars = []): string
    {
        if (!Performer::TYPES[$objectType]) {
            return "Invalid type $objectType";
        }
        $objectName = strtolower($objectName);

        // Check if user is allowed to use this particular action or handler (see EditHandlersAclsAction EditActionsAclsAction)
        if (!$this->wiki->CheckModuleACL($objectName, $objectType)) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('ERROR_NO_ACCESS') . '</div>' . "\n";
        }

        // find object
        $object = isset($this->objectList[$objectType][$objectName]) ? $this->objectList[$objectType][$objectName] : false;
        if (!$object) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('NOT_FOUND') . '</div>' . "\n";
        }

        // the current output
        $output = '';

        // execute main file with callbacks
        $files = array_merge($object['before_callbacks'], [$object], $object['after_callbacks']);
        foreach ($files as $file) {
            try {
                if ($file['isDefinedAsClass']) {
                    $performable = $this->createPerformable($file, $vars, $output);
                    try {
                        $output .= $performable->run();
                    } catch (HttpException $exception) {
                        return $this->renderError($exception->getMessage(), $objectType);
                    }
                } else {
                    $vars['plugin_output_new'] = &$output;
                    // need to run them from YesWiki Class so the variable $this (used in all the plain PHP object) refers to YesWiki, not to Performer service
                    $vars = $this->wiki->runFileInBuffer($file['filePath'], $vars);
                    $output = &$vars['plugin_output_new'];
                    unset($vars['plugin_output_new']);
                }
            } catch (ExitException $t) {
                throw $t;
            } catch (Throwable $t) {
                // catch all errors and exceptions thrown by the execution of the performable
                $message = _t('PERFORMABLE_ERROR');
                $message .= "<br/>{$t->getMessage()} in <i>{$t->getFile()}</i> on line <i>{$t->getLine()}</i>";

                return $this->renderError($message, $objectType);
            }
        }

        return $output;
    }

    private function renderError($message, $objectType)
    {
        $data = [
            'type' => 'danger',
            'message' => $message,
        ];
        if ($objectType == 'handler') {
            // display it with a header and a footer
            return $this->twig->renderInSquelette('@templates/alert-message-with-back.twig', $data);
        } else {
            // display it inline
            return $this->twig->render('@templates/alert-message.twig', $data);
        }
    }

    /**
     * Retrieves the list of existing objects.
     *
     * @return array an unordered array of all the available objects
     */
    public function list($objectType): array
    {
        if (!Performer::TYPES[$objectType]) {
            throw new PerformerException("Invalid type $objectType");
        }

        return array_unique(array_keys($this->objectList[$objectType]));
    }
}
