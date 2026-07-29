<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Wiki;

class EventDispatcher extends SymfonyEventDispatcher
{
    protected $wiki;
    protected ThrowableFormatter $throwableFormatter;

    public function __construct(
        Wiki $wiki,
        ThrowableFormatter $throwableFormatter
    ) {
        parent::__construct();
        $this->wiki = $wiki;
        $this->throwableFormatter = $throwableFormatter;
    }

    public function yesWikiDispatch(string $eventName, array $data = []): array
    {
        try {
            $this->dispatch(new Event($data), $eventName);

            return [];
        } catch (\Throwable $th) {
            $errors = ($this->wiki->userIsAdmin()) ? ['exception' => [
                'message' => $this->throwableFormatter->hideServerPath($th->getMessage()),
                'file' => $this->throwableFormatter->hideServerPath($th->getFile()),
                'line' => $th->getLine(),
                'trace' => $this->throwableFormatter->hideServerPath($th->getTraceAsString()),
            ]] : [];

            return $errors;
        }
    }
}
