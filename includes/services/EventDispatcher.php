<?php

namespace YesWiki\Core\Service;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Throwable;
use YesWiki\Core\Entity\Event;
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

    /**
     * @param array $errors
     */
    public function yesWikiDispatch(string $eventName, array $data = []): array
    {
        try {
            $this->dispatch(new Event($data), $eventName);

            return [];
        } catch (Throwable $th) {
            $errors = ($this->wiki->userIsAdmin()) ? ['exception' => [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]] : [];

            return $errors;
        }
    }
}
