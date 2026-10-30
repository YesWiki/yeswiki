<?php

namespace YesWiki\Kernel\Service;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use YesWiki\Kernel\Entity\Event;

class EventDispatcher extends SymfonyEventDispatcher
{
    protected ContainerInterface $container;
    protected ThrowableFormatter $throwableFormatter;

    public function __construct(
        ContainerInterface $container,
        ThrowableFormatter $throwableFormatter
    ) {
        parent::__construct();
        $this->container = $container;
        $this->throwableFormatter = $throwableFormatter;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function yesWikiDispatch(string $eventName, array $data = []): array
    {
        try {
            $this->dispatch(new Event($data), $eventName);

            return [];
        } catch (\Throwable $th) {
            $errors = ($this->container->get(\YesWiki\Identity\Service\AclService::class)->isAdmin()) ? ['exception' => [
                'message' => $this->throwableFormatter->hideServerPath($th->getMessage()),
                'file' => $this->throwableFormatter->hideServerPath($th->getFile()),
                'line' => $th->getLine(),
                'trace' => $this->throwableFormatter->hideServerPath($th->getTraceAsString()),
            ]] : [];

            return $errors;
        }
    }

    /**
     * Calls each listener of the event on its own: one that throws stops neither the ones after it nor the caller, which hears of it through $onFailure.
     *
     * Unlike yesWikiDispatch(), which catches the first throw around the whole dispatch.
     *
     * @param \Closure(string, \Throwable): void $onFailure given the listener's name (nameOf()) and what it threw
     */
    public function dispatchToEach(string $eventName, Event $event, \Closure $onFailure): void
    {
        foreach ($this->getListeners($eventName) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }
            if (!is_callable($listener)) {
                continue;
            }

            try {
                $listener($event, $eventName, $this);
            } catch (\Throwable $failed) {
                $onFailure(self::nameOf($listener), $failed);
            }
        }
    }

    /** What an operator reading the Journal can find in the code: `Class::method`, or where a closure was written. */
    public static function nameOf(callable $listener): string
    {
        if (is_array($listener)) {
            return (is_object($listener[0]) ? $listener[0]::class : (string)$listener[0]) . '::' . $listener[1];
        }
        if ($listener instanceof \Closure) {
            $function = new \ReflectionFunction($listener);
            if (str_contains($function->getName(), '{closure')) {
                return 'closure in ' . basename((string)$function->getFileName()) . ':' . $function->getStartLine();
            }
            $class = $function->getClosureScopeClass();

            return ($class !== null ? $class->getName() . '::' : '') . $function->getName();
        }
        if (is_object($listener)) {
            return $listener::class . '::__invoke';
        }

        return is_string($listener) ? $listener : 'a listener';
    }
}
