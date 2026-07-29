<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Exception\PerformerException;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Performable\PerformableEvent;
use YesWiki\Render\Service\TemplateEngine;

/**
 * Runs actions (`{{name}}`) and handlers (`/PageName/name`).
 *
 * Two ways to find one, and that is deliberate:
 *
 *  - **core** performables are services, resolved by name through ActionRegistry;
 *  - **extension** performables are still discovered by scanning `actions/` and `handlers/`
 *    inside each extension, because an extension is installed at runtime and cannot be in
 *    the compiled container.
 *
 * Wave-two ticket 06 removed three things that used to live here:
 *
 *  - the `__name.php` / `name__.php` before/after callback convention. Core does not hook
 *    itself any more -- those callbacks were merged into the classes they wrapped -- and
 *    extensions hook through PerformableEvent, dispatched below.
 *  - procedural performables. Every action and handler is a class, so `runFileInBuffer()`
 *    and its output-buffer dispatch are gone.
 *  - the `formatter` type. `wakka` was the only one; Wiki::Format() calls
 *    MarkdownFormatterService directly.
 */
class Performer
{
    public const TYPES = [
        'action' => 'action',
        'handler' => 'handler',
    ];
    public const PATHS = [
        Performer::TYPES['action'] => ['actions/'],
        Performer::TYPES['handler'] => ['handlers/', 'handlers/page/'],
    ];

    protected ContainerInterface $container;
    protected $params;
    protected $twig;
    protected ThrowableFormatter $throwableFormatter;
    protected PerformableArguments $performableArguments;

    /** Extension-provided performables found by scanning; core ones live in the registry. */
    protected $objectList;
    protected ActionRegistry $registry;
    protected EventDispatcher $events;

    public function __construct(
        ContainerInterface $container,
        ParameterBagInterface $params,
        TemplateEngine $twig,
        ActionRegistry $registry,
        EventDispatcher $events,
        ThrowableFormatter $throwableFormatter,
        PerformableArguments $performableArguments
    ) {
        $this->container = $container;
        $this->registry = $registry;
        $this->events = $events;
        $this->params = $params;
        $this->twig = $twig;
        $this->throwableFormatter = $throwableFormatter;
        $this->performableArguments = $performableArguments;

        foreach (Performer::TYPES as $type) {
            $this->objectList[$type] = [];
            foreach ($container->get(ExtensionRegistry::class)->all() as $folder) {
                foreach (Performer::PATHS[$type] as $path) {
                    $this->findObjectInPath($folder . $path, $type);
                }
            }
        }
    }

    /**
     * Record the performable classes an extension ships in $dir.
     */
    private function findObjectInPath($dir, $objectType)
    {
        if (!file_exists($dir) || !($dh = opendir($dir))) {
            return;
        }
        while (($file = readdir($dh)) !== false) {
            if (!preg_match("/^([a-zA-Z0-9_-]+)(\.class)?\.php$/", $file, $matches)) {
                continue;
            }
            $baseName = $matches[1];
            // `__x.php` / `x__.php` used to mean a before/after callback. The convention is
            // retired (ticket 06); such a file is now simply not a performable.
            if (str_starts_with($baseName, '__') || str_ends_with($baseName, '__')) {
                continue;
            }
            if (!str_ends_with($baseName, ucfirst($objectType))) {
                continue;
            }
            $objectName = preg_replace("/{$objectType}$/", '', strtolower($baseName));
            $this->objectList[$objectType][$objectName] = [
                'filePath' => $dir . $file,
                'baseName' => $baseName,
            ];
        }
        closedir($dh);
    }

    /**
     * Build the performable described by $object.
     *
     * @param array $object the object description
     * @param array $vars   the variables defined in the execution context of the object
     * @param       $output the current generated output
     *
     * @return mixed the performable instance
     */
    public function createPerformable(array $object, array &$vars, &$output)
    {
        require_once $object['filePath'];
        $className = $object['baseName'];
        /* extract extension name from path to allow namespace */
        if (preg_match('/(?:extensions[\\\\\\/]([A-Za-z0-9_\\-]+)|(custom))[\\\\\/][a-zA-Z0-9_\\\\\/\\-]+.php$/', $object['filePath'], $matches)) {
            $extensionName = empty($matches[1]) ? $matches[2] : $matches[1];
            $classNameWithNamespace = 'YesWiki\\' . StringUtilService::folderToNamespace($extensionName) . '\\' . $object['baseName'];
            if (class_exists($classNameWithNamespace)) {
                $className = $classNameWithNamespace;
            }
        }
        if (class_exists($className)) {
            /** @var \YesWiki\Core\YesWikiPerformable $instance */
            $instance = new $className();
            $this->prepare($instance, $vars, $output);

            return $instance;
        }
        throw new PerformerException("There were a problem while loading {$className} at {$object['filePath']}. Ensures the class exists");
    }

    /**
     * Run an action or handler.
     *
     * @param       $objectName the object name
     * @param       $objectType a Performer::TYPES key
     * @param array $vars       the arguments given to the performable, keyed by parameter name
     *
     * @return string the generated output
     */
    public function run($objectName, $objectType, array $vars = [], bool $end_elem = false): string
    {
        if (!Performer::TYPES[$objectType]) {
            return "Invalid type $objectType";
        }
        $objectName = strtolower($objectName);

        // Check if user is allowed to use this particular action or handler (see EditHandlersAclsAction EditActionsAclsAction)
        // inline FQCN, not an import: ModuleAclService lives in Identity and Kernel may
        // depend on no feature module (ArchitectureTest is import-based)
        if (!$this->container->get(\YesWiki\Identity\Service\ModuleAclService::class)->checkModuleAcl($objectName, $objectType)) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('ERROR_NO_ACCESS') . '</div>' . "\n";
        }

        // Extensions hook here rather than by dropping a __name.php next to ours. Dispatched
        // around both resolution paths, since extension performables are the ones being hooked.
        $output = $this->dispatchPerformableEvent($objectType, $objectName, $vars, PerformableEvent::BEFORE);

        $instance = $this->registry->get($objectType, $objectName);
        if ($instance === null) {
            $object = $this->objectList[$objectType][$objectName] ?? null;
            if ($object === null) {
                return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('NOT_FOUND') . '</div>' . "\n";
            }
        }

        try {
            if ($instance === null) {
                $instance = $this->createPerformable($object, $vars, $output);
            } else {
                $this->prepare($instance, $vars, $output);
            }

            // end() is declared on YesWikiAction, not on the YesWikiPerformable base: only
            // graphical-element actions ({{col}}...{{end}}) have a closing form.
            $output .= ($end_elem && $instance instanceof YesWikiAction)
                ? $instance->end()
                : $instance->run();
        } catch (ExitException $t) {
            throw $t;
        } catch (HttpException $exception) {
            return $this->renderError($exception->getMessage(), $objectType);
        } catch (\Throwable $t) {
            return $this->renderError(
                _t('PERFORMABLE_ERROR') . '<br/>' . $this->throwableFormatter->dump($t),
                $objectType
            );
        }

        return $output . $this->dispatchPerformableEvent($objectType, $objectName, $vars, PerformableEvent::AFTER);
    }

    /**
     * Hand a performable its execution context.
     *
     * @param array<mixed> $vars
     */
    private function prepare(\YesWiki\Core\YesWikiPerformable $instance, array &$vars, string &$output): void
    {
        $instance->setServices($this->container);
        $instance->setParams($this->params);
        $instance->setArguments($vars);
        $instance->setOutput($output);
        $instance->setTwig($this->twig);

        // the request-global raw-argument channel (historic Wiki::$parameter)
        $this->performableArguments->bind($vars);
    }

    /**
     * Fire the performable events for one phase and fold the result back in.
     *
     * A `before` listener may rewrite $vars -- which is what most of the retired before-hooks
     * actually did; they existed to adjust an argument, not to print.
     *
     * @param array<mixed> $vars
     */
    private function dispatchPerformableEvent(string $type, string $name, array &$vars, string $phase): string
    {
        $event = new PerformableEvent($type, $name, $vars);
        foreach ($event->eventNames($phase) as $eventName) {
            $this->events->dispatch($event, $eventName);
        }
        if ($phase === PerformableEvent::BEFORE) {
            $vars = $event->getArguments();
        }

        return $event->getOutput();
    }

    private function renderError($message, $objectType)
    {
        $data = [
            'type' => 'danger',
            'message' => $message,
        ];
        if ($objectType == 'handler') {
            // display it with a header and a footer
            return $this->twig->renderFullPage('@core/alert-message-with-back.twig', $data);
        }

        // display it inline
        return $this->twig->render('@core/alert-message.twig', $data);
    }

    /**
     * Every available performable of $objectType: core services plus extension classes.
     *
     * @return list<string>
     */
    public function list($objectType): array
    {
        if (!Performer::TYPES[$objectType]) {
            throw new PerformerException("Invalid type $objectType");
        }

        return array_values(array_unique(array_merge(
            array_keys($this->objectList[$objectType]),
            $this->registry->names($objectType)
        )));
    }
}
