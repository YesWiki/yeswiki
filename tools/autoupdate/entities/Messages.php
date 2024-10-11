<?php

namespace YesWiki\AutoUpdate\Entity;

class Messages extends Collection
{
    public function reset()
    {
        $this->list = [];

        return $this->list;
    }

    public function add($message, $status)
    {
        $this[] = [
            'text' => _t($message),
            'status' => _t($status),
        ];

        return $this;
    }
}
