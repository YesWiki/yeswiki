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
}
