<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Wiki;

class EventDispatcher extends SymfonyEventDispatcher
{
    protected $wiki;

    public function __construct(
        Wiki $wiki
    ) {
        parent::__construct();
        $this->wiki = $wiki;
    }

    public function yesWikiDispatch(string $eventName, array $data = []): array
    {
        try {
            $this->dispatch(new Event($data), $eventName);

            return [];
        } catch (\Throwable $th) {
            $errors = ($this->wiki->userIsAdmin()) ? ['exception' => [
                'message' => $this->wiki->hideServerPath($th->getMessage()),
                'file' => $this->wiki->hideServerPath($th->getFile()),
                'line' => $th->getLine(),
                'trace' => $this->wiki->hideServerPath($th->getTraceAsString()),
            ]] : [];

            return $errors;
        }
    }
}
