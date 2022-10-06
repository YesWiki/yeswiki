<?php

namespace YesWiki\Core\Entity;

class Event
{
    protected $data ;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
