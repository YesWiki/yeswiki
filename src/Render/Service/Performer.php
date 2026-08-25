<?php

namespace YesWiki\Render\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use YesWiki\Core\YesWikiAction;
use YesWiki\Kernel\Exception\ExitException;
use YesWiki\Kernel\Exception\PerformerException;
use YesWiki\Kernel\Performable\ActionRegistry;
use YesWiki\Kernel\Performable\PerformableEvent;
use YesWiki\Kernel\Service\ClassDirectoryScanner;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\ExtensionRegistry;
use YesWiki\Kernel\Service\PerformableArguments;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Kernel\Service\ThrowableFormatter;

/** Runs actions (`{{name}}`) and handlers (`/PageName/name`). */
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
    protected ParameterBagInterface $params;
    protected TemplateEngine $twig;
    protected ThrowableFormatter $throwableFormatter;
    protected PerformableArguments $performableArguments;

    /**
     * Extension-provided performables found by scanning; core ones live in the registry.
     *
     * @var array<string, array<string, array{filePath: string, baseName: string}>> type => performable name => where its class lives
     */
    protected $objectList = [];
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
    private function findObjectInPath(string $dir, string $objectType): void
    {
        // Through the scanner rather than opendir: these are code directories -- a module's own
        // `Action/`, or an extension's -- which ADR-0022 keeps outside Storage's tiers because
        // they hold PHP that gets included rather than a wiki's data. One place reads them
        // (ticket 49), and this was the last one that did not.
        foreach ($this->container->get(ClassDirectoryScanner::class)->filesIn($dir) as $file) {
            if (!preg_match("/^([a-zA-Z0-9_-]+)(\.class)?\.php$/", $file, $matches)) {
                continue;
            }
            $baseName = $matches[1];
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
    }

    /**
     * Build the performable described by $object.
     *
     * @param array{filePath: string, baseName: string} $object the object description
     * @param array<mixed>                              $vars   the variables defined in the execution context of the object
     * @param string                                    $output the current generated output
     *
     * @return \YesWiki\Core\YesWikiPerformable the performable instance
     */
    public function createPerformable(array $object, array &$vars, &$output)
    {
        require_once $object['filePath'];
        $className = $object['baseName'];
        if (preg_match('/(?:extensions[\\\\\\/]([A-Za-z0-9_\\-]+)|(custom))[\\\\\/][a-zA-Z0-9_\\\\\/\\-]+.php$/', $object['filePath'], $matches)) {
            $extensionName = empty($matches[1]) ? ($matches[2] ?? 'custom') : $matches[1];
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
     * @param string       $objectName the object name
     * @param string       $objectType a Performer::TYPES key
     * @param array<mixed> $vars       the arguments given to the performable, keyed by parameter name
     *
     * @return string the generated output
     */
    public function run($objectName, $objectType, array $vars = [], bool $end_elem = false): string
    {
        if (!isset(Performer::TYPES[$objectType])) {
            return "Invalid type $objectType";
        }
        $objectName = strtolower($objectName);

        [$objectName, $aliasDefaults] = $this->registry->resolve($objectType, $objectName);
        if ($aliasDefaults !== []) {
            $vars += $aliasDefaults;
        }

        if (!$this->container->get(\YesWiki\Identity\Service\ModuleAclService::class)->checkModuleAcl($objectName, $objectType)) {
            return '<div class="alert alert-danger">' . ucfirst($objectType) . " $objectName : " . _t('ERROR_NO_ACCESS') . '</div>' . "\n";
        }

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

        $this->performableArguments->bind($vars);
    }

    /**
     * Fire the performable events for one phase and fold the result back in.
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

    private function renderError(string $message, string $objectType): string
    {
        $data = [
            'type' => 'danger',
            'message' => $message,
        ];
        if ($objectType == 'handler') {
            return $this->twig->renderFullPage('@core/alert-message-with-back.twig', $data);
        }

        return $this->twig->render('@core/alert-message.twig', $data);
    }

    /**
     * Every available performable of $objectType: core services plus extension classes.
     *
     * @param string $objectType a Performer::TYPES key
     *
     * @return list<string>
     */
    public function list($objectType): array
    {
        if (!isset(Performer::TYPES[$objectType])) {
            throw new PerformerException("Invalid type $objectType");
        }

        return array_values(array_unique(array_merge(
            array_map('strval', array_keys($this->objectList[$objectType])),
            $this->registry->names($objectType)
        )));
    }
}
