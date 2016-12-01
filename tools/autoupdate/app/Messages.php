<?php
namespace AutoUpdate;

class Messages extends Collection
{
    public function reset()
    {
        $this->list = array();
        return $this->list;
    }

    public function add($message, $status)
    {
        $this[] = array(
            'text' => _t($message),
            'status' => _t($status),
        );
        return $this;
    }
}
