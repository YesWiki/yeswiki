<?php

namespace YesWiki\Core\Entity;

use Symfony\Contracts\EventDispatcher\Event as SymfonyEvent;

class Event extends SymfonyEvent
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
